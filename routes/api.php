<?php

use App\Http\Controllers\Api\V1\Admin\DashboardStatsController;
use App\Http\Controllers\Api\V1\Admin\PartnerApprovalController;
use App\Http\Controllers\Api\V1\B2B\AuthController as B2BAuthController;
use App\Http\Controllers\Api\V1\B2B\CrmController;
use App\Http\Controllers\Api\V1\B2B\LeadMarketplaceController;
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
    | B2B — partner portal
    |--------------------------------------------------------------------------
    */
    Route::prefix('b2b')->group(function (): void {
        Route::post('/auth/login', [B2BAuthController::class, 'login'])
            ->middleware('throttle:auth-otp-verify');

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

            Route::get('/marketplace/leads', [LeadMarketplaceController::class, 'index']);
            Route::get('/marketplace', [LeadMarketplaceController::class, 'index']);
            Route::post('/marketplace/leads/{id}/unlock', [LeadMarketplaceController::class, 'unlock']);
            Route::post('/leads/{id}/unlock', [LeadMarketplaceController::class, 'unlock']);

            Route::get('/crm/clients', [CrmController::class, 'index']);
            Route::patch('/crm/clients/{id}', [CrmController::class, 'update']);

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
        Route::get('/dashboard/stats', [DashboardStatsController::class, 'index']);
        Route::get('/metrics', [DashboardStatsController::class, 'index']);

        Route::get('/revenue/timeline', NotImplementedController::class);
        Route::get('/leads/flow', NotImplementedController::class);
        Route::get('/portfolio/summary', NotImplementedController::class);
        Route::get('/portfolio/allocation', NotImplementedController::class);
        Route::get('/portfolio/partners', NotImplementedController::class);
        Route::get('/risk-indicators', NotImplementedController::class);

        Route::get('/transactions', NotImplementedController::class);
        Route::get('/transactions/{id}', NotImplementedController::class);

        Route::get('/partners', NotImplementedController::class);
        Route::get('/partners/{company}', NotImplementedController::class);
        Route::post('/partners/{company}/approve', [PartnerApprovalController::class, 'approve']);
        Route::post('/partners/{company}/reject', [PartnerApprovalController::class, 'reject']);
        Route::post('/partners/{company}/suspend', NotImplementedController::class);
        Route::post('/partners/{company}/impersonate', NotImplementedController::class);

        Route::post('/companies/{company}/approve', [PartnerApprovalController::class, 'approve']);
        Route::post('/companies/{company}/reject', [PartnerApprovalController::class, 'reject']);

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
