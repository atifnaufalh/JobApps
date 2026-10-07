<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
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
        RateLimiter::for('firebase-auth', function (Request $request): array {
            return [
                Limit::perMinutes(15, 10)->by('firebase-auth-short:'.$request->ip()),
                Limit::perHour(30)->by('firebase-auth-hour:'.$request->ip()),
            ];
        });
    }
}
