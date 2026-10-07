<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('otp', function (Request $request): array {
            $email = Str::lower((string) $request->input('email', ''));

            return [
                Limit::perMinutes(15, 10)->by('otp-ip:'.$request->ip()),
                Limit::perHour(5)->by('otp-email:'.$email),
            ];
        });
    }
}
