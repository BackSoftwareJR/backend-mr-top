<?php

use App\Http\Controllers\Api\V1\B2C\LeadSubmissionController;
use App\Http\Controllers\Api\V1\ConsentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\NotImplementedController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);
    /*
    |--------------------------------------------------------------------------
    | Cross-cutting (consents, health-adjacent)
    |--------------------------------------------------------------------------
    */
    Route::post('/consents', [ConsentController::class, 'store'])
        ->middleware('throttle:30,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/consents/me', [ConsentController::class, 'me']);
    });

    /*
    |--------------------------------------------------------------------------
    | B2C — public wizard & consumer intake
    |--------------------------------------------------------------------------
    */
    Route::prefix('b2c')->group(function (): void {
        Route::post('/leads', [LeadSubmissionController::class, 'store'])
            ->middleware('throttle:wizard-submit');

        Route::get('/sectors/{slug}/wizard', NotImplementedController::class);
        Route::get('/leads/{uuid}/status', NotImplementedController::class);
        Route::get('/leads/{uuid}/results', NotImplementedController::class);
        Route::get('/locations/autocomplete', NotImplementedController::class);
    });

    /*
    |--------------------------------------------------------------------------
    | B2B — partner portal (approved partners)
    |--------------------------------------------------------------------------
    */
    Route::prefix('b2b')->group(function (): void {
        Route::post('/register', NotImplementedController::class);

        Route::middleware(['auth:sanctum', 'role:partner'])->group(function (): void {
            Route::get('/onboarding', NotImplementedController::class);
            Route::patch('/onboarding', NotImplementedController::class);
            Route::post('/onboarding/documents', NotImplementedController::class);
            Route::post('/onboarding/submit', NotImplementedController::class);
            Route::get('/onboarding/status', NotImplementedController::class);

            Route::get('/dashboard', NotImplementedController::class);
            Route::get('/wallet', NotImplementedController::class);
            Route::post('/wallet/recharge', NotImplementedController::class);
            Route::get('/wallet/transactions', NotImplementedController::class);

            Route::get('/marketplace/leads', NotImplementedController::class);
            Route::post('/marketplace/leads/{id}/unlock', NotImplementedController::class);

            Route::get('/crm/clients', NotImplementedController::class);
            Route::patch('/crm/clients/{id}', NotImplementedController::class);

            Route::get('/appointments', NotImplementedController::class);
            Route::post('/appointments', NotImplementedController::class);

            Route::get('/notifications', NotImplementedController::class);
            Route::patch('/notifications/{id}/read', NotImplementedController::class);
            Route::post('/notifications/read-all', NotImplementedController::class);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Admin — God Mode (superadmin only)
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->middleware(['auth:sanctum', 'role:superadmin', 'throttle:admin'])->group(function (): void {
        Route::get('/metrics', NotImplementedController::class);
        Route::get('/revenue/timeline', NotImplementedController::class);
        Route::get('/leads/flow', NotImplementedController::class);
        Route::get('/portfolio/summary', NotImplementedController::class);
        Route::get('/portfolio/allocation', NotImplementedController::class);
        Route::get('/portfolio/partners', NotImplementedController::class);
        Route::get('/risk-indicators', NotImplementedController::class);

        Route::get('/transactions', NotImplementedController::class);
        Route::get('/transactions/{id}', NotImplementedController::class);

        Route::get('/partners', NotImplementedController::class);
        Route::get('/partners/{id}', NotImplementedController::class);
        Route::post('/partners/{id}/approve', NotImplementedController::class);
        Route::post('/partners/{id}/reject', NotImplementedController::class);
        Route::post('/partners/{id}/suspend', NotImplementedController::class);
        Route::post('/partners/{id}/impersonate', NotImplementedController::class);

        Route::get('/leads', NotImplementedController::class);
        Route::get('/leads/{id}', NotImplementedController::class);
        Route::patch('/leads/{id}/assign', NotImplementedController::class);
        Route::post('/leads/{id}/reroute', NotImplementedController::class);

        Route::get('/settings', NotImplementedController::class);
        Route::patch('/settings', NotImplementedController::class);
        Route::get('/sectors', NotImplementedController::class);
        Route::patch('/sectors/{id}', NotImplementedController::class);

        Route::get('/notifications', NotImplementedController::class);
    });

    /*
    |--------------------------------------------------------------------------
    | Auth placeholders (P0 — OTP flow TBD)
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function (): void {
        Route::post('/otp/request', NotImplementedController::class)
            ->middleware('throttle:auth-otp-request');
        Route::post('/otp/verify', NotImplementedController::class)
            ->middleware('throttle:auth-otp-verify');
        Route::get('/resend-cooldown', NotImplementedController::class)
            ->middleware('throttle:auth-otp-request');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', NotImplementedController::class);
            Route::get('/me', NotImplementedController::class);
        });
    });
});
