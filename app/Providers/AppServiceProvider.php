<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Company;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::bind('company', function (string $value): Company {
            return Company::query()->where('uuid', $value)->firstOrFail();
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth-otp-request', function (Request $request) {
            $email = (string) $request->input('email', '');

            return Limit::perMinutes(15, 3)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('auth-otp-verify', function (Request $request) {
            $email = (string) $request->input('email', '');

            return Limit::perMinute(10)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('wizard-submit', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(300)->by($request->user()?->id ?: $request->ip());
        });
    }
}
