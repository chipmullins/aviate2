<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\MeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cookie-based login for the Angular app (Laravel Sanctum "SPA" mode).
 * The browser first calls GET /sanctum/csrf-cookie, then these endpoints.
 */
class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => User::generateUsername($data['first_name'], $data['last_name']),
            'email' => $data['email'],
            'password' => $data['password'], // hashed by the model's cast
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return (new MeResource($user))->response()->setStatusCode(201);
    }

    public function login(Request $request): MeResource
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $email = Str::lower(trim($credentials['email']));
        $throttleKey = 'login:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Try again in $seconds seconds.",
            ])->status(429);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $credentials['password']], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            // Same message whether the email exists or not, so accounts can't be discovered.
            throw ValidationException::withMessages(['email' => 'Email or password is incorrect.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        // Logging back in reopens a closed account (same as the original Aviate).
        if ($user->isClosed()) {
            $user->forceFill(['closed_at' => null])->save();
        }

        return new MeResource($user);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): MeResource
    {
        return new MeResource($request->user());
    }
}
