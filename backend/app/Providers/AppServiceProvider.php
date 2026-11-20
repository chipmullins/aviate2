<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Catch lazy-loading and typo'd attributes while developing.
        Model::shouldBeStrict(! $this->app->isProduction());

        $key = fn (Request $request) => $request->user()?->id ?: $request->ip();

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(240)->by($key($r)));
        // Login also has its own per-email limit in AuthController.
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('posting', fn (Request $r) => Limit::perMinute(30)->by($key($r)));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(10)->by($key($r)));
    }
}
