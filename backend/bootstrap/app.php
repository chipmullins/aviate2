<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // /api/broadcasting/auth: Reverb asks here whether the signed-in user may join a private channel.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']])
    ->withMiddleware(function (Middleware $middleware): void {
        // Lets the Angular app (same site) authenticate with session cookies + CSRF protection.
        $middleware->statefulApi();
        $middleware->throttleApi('api');
        // Trust X-Forwarded-For only from proxies you name (e.g. an AWS load balancer: TRUSTED_PROXIES=*).
        // By default none are trusted, so clients can't fake their IP to dodge rate limits.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'sanctum/*') || $request->expectsJson(),
        );
    })->create();
