<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * If an account was closed (for example from another browser), end this session too.
 * Logging in again is what reopens an account.
 */
class EnsureAccountIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isClosed()) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            abort(401, 'This account is closed. Log in to reopen it.');
        }

        return $next($request);
    }
}
