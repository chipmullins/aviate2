<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\MeResource;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/** The Settings page. */
class AccountController extends Controller
{
    public function __construct(private ImageService $images)
    {
    }

    public function updateProfile(UpdateProfileRequest $request): MeResource
    {
        $this->refuseDemoAccount($request);
        $user = $request->user();
        $user->update($request->validated());

        return new MeResource($user->refresh());
    }

    public function updatePassword(UpdatePasswordRequest $request): Response
    {
        $this->refuseDemoAccount($request);
        $request->user()->update(['password' => $request->validated('password')]);
        $request->session()->regenerate();

        return response()->noContent();
    }

    public function updateAvatar(Request $request): MeResource
    {
        $this->refuseDemoAccount($request);
        $maxKb = (int) config('aviate.uploads.max_kilobytes');
        $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', "max:$maxKb", 'dimensions:max_width=6000,max_height=6000'],
        ]);

        $user = $request->user();
        $old = $user->avatar_path;
        $user->update(['avatar_path' => $this->images->storeAvatar($request->file('avatar'))]);
        $this->images->delete($old);

        return new MeResource($user);
    }

    public function deleteAvatar(Request $request): MeResource
    {
        $this->refuseDemoAccount($request);
        $user = $request->user();
        $this->images->delete($user->avatar_path);
        $user->update(['avatar_path' => null]);

        return new MeResource($user);
    }

    /** Hides the profile and all activity. Logging back in reopens it. Requires the password. */
    public function close(Request $request): Response
    {
        $this->refuseDemoAccount($request);
        $request->validate(['password' => ['required', 'string', 'current_password']]);

        $request->user()->forceFill(['closed_at' => now()])->save();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /** On a demo server everyone shares the sample accounts, so nobody may lock the others out. */
    private function refuseDemoAccount(Request $request): void
    {
        abort_if($request->user()->isLockedDemoAccount(), 403, 'Demo accounts can’t be changed. Create your own account to try Settings.');
    }
}
