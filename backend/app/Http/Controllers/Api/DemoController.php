<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** One-click sign-in as a sample crew member, only when the server runs in demo mode. */
class DemoController extends Controller
{
    /** What the login page needs: whether demo mode is on, and which accounts to offer. */
    public function show(Request $request): JsonResponse
    {
        if (! config('aviate.demo.enabled')) {
            return response()->json(['enabled' => false, 'accounts' => []]);
        }

        $featured = collect(config('aviate.demo.featured'));
        $users = User::active()
            ->where('is_demo', true)
            ->whereIn('username', $featured->pluck('username'))
            ->get()
            ->keyBy('username');

        $accounts = $featured
            ->filter(fn ($f) => $users->has($f['username']))
            ->map(fn ($f) => [
                ...(new UserResource($users[$f['username']]))->resolve($request),
                'blurb' => $f['blurb'],
            ])
            ->values();

        return response()->json([
            'enabled' => true,
            'reset_at' => config('aviate.demo.reset_at'),
            'time_zone' => config('aviate.demo.time_zone'),
            'accounts' => $accounts,
        ]);
    }

    public function login(Request $request): MeResource
    {
        abort_unless(config('aviate.demo.enabled'), 404);

        $data = $request->validate(['username' => ['required', 'string', 'max:60']]);

        // Only the seeded sample accounts, never a real person's account.
        $user = User::where('is_demo', true)->where('username', $data['username'])->firstOrFail();

        if ($user->isClosed()) {
            $user->forceFill(['closed_at' => null])->save();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return new MeResource($user);
    }
}
