<?php

use App\Http\Controllers\Api\V1\Admin\AnalyticsController;
use App\Http\Controllers\Api\V1\Admin\DashboardStatsController;
use App\Http\Controllers\Api\V1\Admin\LeadsController as AdminLeadsController;
use App\Http\Controllers\Api\V1\Admin\PartnerApprovalController;
use App\Http\Controllers\Api\V1\Admin\PartnersController;
use App\Http\Controllers\Api\V1\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\V1\Admin\TransactionsController as AdminTransactionsController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\B2B\AppointmentsController;
use App\Http\Controllers\Api\V1\B2B\AuthController as B2BAuthController;
use App\Http\Controllers\Api\V1\B2B\CompanyProfileController;
use App\Http\Controllers\Api\V1\B2B\CrmController;
use App\Http\Controllers\Api\V1\B2B\DashboardController as B2BDashboardController;
use App\Http\Controllers\Api\V1\B2B\LeadMarketplaceController;
use App\Http\Controllers\Api\V1\B2B\OnboardingController;
use App\Http\Controllers\Api\V1\B2B\RegisterController;
use App\Http\Controllers\Api\V1\B2B\WalletController;
use App\Http\Controllers\Api\V1\B2C\LeadResultsController;
use App\Http\Controllers\Api\V1\B2C\LeadSubmissionController;
use App\Http\Controllers\Api\V1\B2C\LocationsController;
use App\Http\Controllers\Api\V1\B2C\WizardController;
use App\Http\Controllers\Api\V1\ConsentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\User\UserAreaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);

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
        Route::get('/sectors', [WizardController::class, 'sectors']);
        Route::get('/sectors/{slug}/wizard', [WizardController::class, 'show']);
        Route::get('/locations/autocomplete', [LocationsController::class, 'autocomplete']);

        Route::post('/leads', [LeadSubmissionController::class, 'store'])
            ->middleware('throttle:wizard-submit');

        Route::get('/leads/{uuid}', [LeadResultsController::class, 'show']);
        Route::get('/leads/{uuid}/status', [LeadResultsController::class, 'status']);
        Route::get('/leads/{uuid}/results', [LeadResultsController::class, 'results']);
        Route::get('/leads/{uuid}/matches', [LeadResultsController::class, 'matches']);
    });

    /*
    |--------------------------------------------------------------------------
    | Consumer authenticated area
    |--------------------------------------------------------------------------
    */
    Route::prefix('user')->middleware(['auth:sanctum', 'role:consumer'])->group(function (): void {
        Route::get('/home', [UserAreaController::class, 'home']);
        Route::get('/searches', [UserAreaController::class, 'searches']);
        Route::get('/searches/{id}', [UserAreaController::class, 'searchShow']);
        Route::post('/leads', [UserAreaController::class, 'attachLead']);
        Route::get('/profile', [UserAreaController::class, 'profile']);
        Route::patch('/profile', [UserAreaController::class, 'updateProfile']);
        Route::get('/saved-matches', [UserAreaController::class, 'savedMatches']);
        Route::post('/saved-matches', [UserAreaController::class, 'toggleSavedMatch']);
    });

    /*
    |--------------------------------------------------------------------------
    | B2B — partner portal
    |--------------------------------------------------------------------------
    */
    Route::prefix('b2b')->group(function (): void {
        Route::post('/auth/login', [B2BAuthController::class, 'login'])
            ->middleware('throttle:auth-otp-verify');

        Route::post('/register', [RegisterController::class, 'store']);

        Route::middleware(['auth:sanctum', 'role:partner'])->group(function (): void {
            Route::get('/onboarding', [OnboardingController::class, 'show']);
            Route::patch('/onboarding', [OnboardingController::class, 'update']);
            Route::post('/onboarding/documents', [OnboardingController::class, 'uploadDocument']);
            Route::post('/onboarding/submit', [OnboardingController::class, 'submit']);
            Route::get('/onboarding/status', [OnboardingController::class, 'status']);

            Route::get('/company/profile', [CompanyProfileController::class, 'show']);
            Route::patch('/company/profile', [CompanyProfileController::class, 'update']);

            Route::get('/dashboard', [B2BDashboardController::class, 'index']);
            Route::get('/wallet', [WalletController::class, 'show']);
            Route::post('/wallet/recharge', [WalletController::class, 'recharge']);
            Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
            Route::get('/invoices', [WalletController::class, 'invoices']);

            Route::get('/marketplace/leads', [LeadMarketplaceController::class, 'index']);
            Route::get('/marketplace', [LeadMarketplaceController::class, 'index']);
            Route::post('/marketplace/leads/{id}/unlock', [LeadMarketplaceController::class, 'unlock']);
            Route::post('/leads/{id}/unlock', [LeadMarketplaceController::class, 'unlock']);

            Route::get('/crm/clients', [CrmController::class, 'index']);
            Route::patch('/crm/clients/{id}', [CrmController::class, 'update']);

            Route::get('/appointments', [AppointmentsController::class, 'index']);
            Route::post('/appointments', [AppointmentsController::class, 'store']);

            Route::get('/notifications', [B2BDashboardController::class, 'notifications']);
            Route::patch('/notifications/{id}/read', [B2BDashboardController::class, 'markNotificationRead']);
            Route::post('/notifications/read-all', [B2BDashboardController::class, 'markAllNotificationsRead']);
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

        Route::get('/revenue/timeline', [AnalyticsController::class, 'revenueTimeline']);
        Route::get('/leads/flow', [AnalyticsController::class, 'leadsFlow']);
        Route::get('/portfolio/summary', [AnalyticsController::class, 'portfolioSummary']);
        Route::get('/portfolio/allocation', [AnalyticsController::class, 'portfolioAllocation']);
        Route::get('/portfolio/partners', [AnalyticsController::class, 'portfolioPartners']);
        Route::get('/risk-indicators', [AnalyticsController::class, 'riskIndicators']);

        Route::get('/transactions', [AdminTransactionsController::class, 'index']);
        Route::get('/transactions/{transaction}', [AdminTransactionsController::class, 'show']);

        Route::get('/companies', [PartnersController::class, 'index']);
        Route::get('/partners', [PartnersController::class, 'index']);
        Route::get('/partners/{company}', [PartnersController::class, 'show']);
        Route::post('/partners/{company}/approve', [PartnerApprovalController::class, 'approve']);
        Route::post('/partners/{company}/reject', [PartnerApprovalController::class, 'reject']);
        Route::post('/partners/{company}/suspend', [PartnersController::class, 'suspend']);
        Route::post('/partners/{company}/impersonate', [PartnersController::class, 'impersonate']);

        Route::post('/companies/{company}/approve', [PartnerApprovalController::class, 'approve']);
        Route::post('/companies/{company}/reject', [PartnerApprovalController::class, 'reject']);

        Route::get('/leads', [AdminLeadsController::class, 'index']);
        Route::get('/leads/{id}', [AdminLeadsController::class, 'show']);
        Route::patch('/leads/{id}/assign', [AdminLeadsController::class, 'assign']);
        Route::post('/leads/{id}/reroute', [AdminLeadsController::class, 'reroute']);

        Route::get('/settings', [AdminSettingsController::class, 'show']);
        Route::patch('/settings', [AdminSettingsController::class, 'update']);
        Route::get('/sectors', [AdminSettingsController::class, 'sectors']);
        Route::patch('/sectors/{id}', [AdminSettingsController::class, 'updateSector']);

        Route::get('/notifications', [AdminSettingsController::class, 'notifications']);
    });

    /*
    |--------------------------------------------------------------------------
    | Auth — shared OTP (consumer, partner, admin)
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->group(function (): void {
        Route::post('/otp/request', [OtpController::class, 'request'])
            ->middleware('throttle:auth-otp-request');
        Route::post('/otp/verify', [OtpController::class, 'verify'])
            ->middleware('throttle:auth-otp-verify');
        Route::get('/resend-cooldown', [OtpController::class, 'resendCooldown'])
            ->middleware('throttle:auth-otp-request');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [SessionController::class, 'logout']);
            Route::get('/me', [SessionController::class, 'me']);
        });
    });
});
