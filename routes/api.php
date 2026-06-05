<?php

use App\Http\Controllers\Api\V1\Admin\AdvisorBookingsController;
use App\Http\Controllers\Api\V1\Admin\AnalyticsController;
use App\Http\Controllers\Api\V1\Admin\CompanyVettingController;
use App\Http\Controllers\Api\V1\Admin\DashboardStatsController;
use App\Http\Controllers\Api\V1\Admin\LeadsController as AdminLeadsController;
use App\Http\Controllers\Api\V1\Admin\PartnerApprovalController;
use App\Http\Controllers\Api\V1\Admin\PartnersController;
use App\Http\Controllers\Api\V1\Admin\PrivacyErasureController;
use App\Http\Controllers\Api\V1\Admin\SearchController;
use App\Http\Controllers\Api\V1\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\V1\Admin\TransactionsController as AdminTransactionsController;
use App\Http\Controllers\Api\V1\Admin\WalletController as AdminWalletController;
use App\Http\Controllers\Api\V1\Admin\WebhooksController as AdminWebhooksController;
use App\Http\Controllers\Api\V1\Auth\OtpController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\B2B\AppointmentsController;
use App\Http\Controllers\Api\V1\B2B\AuthController as B2BAuthController;
use App\Http\Controllers\Api\V1\B2B\CompanyProfileController;
use App\Http\Controllers\Api\V1\B2B\CoverageZoneController;
use App\Http\Controllers\Api\V1\B2B\CrmController;
use App\Http\Controllers\Api\V1\B2B\DashboardController as B2BDashboardController;
use App\Http\Controllers\Api\V1\B2B\LeadMarketplaceController;
use App\Http\Controllers\Api\V1\B2B\OnboardingController;
use App\Http\Controllers\Api\V1\B2B\RegisterController;
use App\Http\Controllers\Api\V1\B2B\SmartCrmController;
use App\Http\Controllers\Api\V1\B2B\WalletController;
use App\Http\Controllers\Api\V1\B2C\AdvisorController;
use App\Http\Controllers\Api\V1\B2C\LeadResultsController;
use App\Http\Controllers\Api\V1\B2C\LeadSubmissionController;
use App\Http\Controllers\Api\V1\B2C\LocationsController;
use App\Http\Controllers\Api\V1\B2C\NandoController;
use App\Http\Controllers\Api\V1\B2C\WizardController;
use App\Http\Controllers\Api\V1\ConsentController;
use App\Http\Controllers\Api\V1\GeoController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\User\AdvisorBookingController;
use App\Http\Controllers\Api\V1\User\PrivacyController;
use App\Http\Controllers\Api\V1\User\UserAreaController;
use App\Http\Controllers\Api\V1\Webhooks\PaymentWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\StripePaymentWebhookController;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);

    Route::get('/geo/search', [GeoController::class, 'search'])
        ->middleware('throttle:geo-search');
    Route::get('/geo/reverse', [GeoController::class, 'reverse'])
        ->middleware('throttle:geo-search');

    Route::post('/webhooks/payments/stripe', [StripePaymentWebhookController::class, 'handle'])
        ->middleware('throttle:60,1');

    Route::post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'handle'])
        ->where('provider', 'mock|mollie')
        ->middleware(['wenando.webhook', 'throttle:60,1']);

    Route::post('/login', [B2BAuthController::class, 'login'])
        ->middleware('throttle:auth-otp-verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    Route::post('/consents', [ConsentController::class, 'store'])
        ->middleware('throttle:30,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/consents/me', [ConsentController::class, 'me']);
        Route::patch('/consents/me', [ConsentController::class, 'update'])
            ->middleware('throttle:30,1');
    });

    /*
    |--------------------------------------------------------------------------
    | B2C — public wizard & consumer intake
    |--------------------------------------------------------------------------
    */
    Route::prefix('b2c')->group(function (): void {
        Route::get('/sectors', [WizardController::class, 'sectors']);
        Route::get('/sectors/{slug}/wizard', [WizardController::class, 'show']);
        Route::get('/locations/autocomplete', [LocationsController::class, 'autocomplete'])
            ->middleware('throttle:locations-autocomplete');
        Route::get('/advisor', [AdvisorController::class, 'show']);

        Route::post('/nando/refine', [NandoController::class, 'refine'])
            ->middleware('throttle:nando-refine');

        Route::post('/leads', [LeadSubmissionController::class, 'store'])
            ->middleware('throttle:wizard-submit');

        Route::get('/leads/{uuid}', [LeadResultsController::class, 'show']);
        Route::get('/leads/{uuid}/status', [LeadResultsController::class, 'status']);
        Route::get('/leads/{uuid}/results', [LeadResultsController::class, 'results'])
            ->middleware('throttle:b2c-lead-results');
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
        Route::get('/searches/{lead}', [UserAreaController::class, 'searchShow']);
        Route::patch('/searches/{lead}', [UserAreaController::class, 'updateSearch']);
        Route::post('/leads', [UserAreaController::class, 'attachLead']);
        Route::get('/profile', [UserAreaController::class, 'profile']);
        Route::patch('/profile', [UserAreaController::class, 'updateProfile']);
        Route::get('/saved-matches', [UserAreaController::class, 'savedMatches']);
        Route::post('/saved-matches', [UserAreaController::class, 'toggleSavedMatch']);
        Route::get('/advisor-bookings', [AdvisorBookingController::class, 'index']);
        Route::post('/advisor-bookings', [AdvisorBookingController::class, 'store'])
            ->middleware('throttle:30,1');
        Route::patch('/advisor-bookings/{advisorBooking}', [AdvisorBookingController::class, 'update'])
            ->middleware('throttle:30,1');
        Route::delete('/advisor-bookings/{advisorBooking}', [AdvisorBookingController::class, 'destroy'])
            ->middleware('throttle:30,1');
        Route::get('/privacy/export', [PrivacyController::class, 'export'])
            ->middleware('throttle:10,1');
        Route::post('/privacy/erase-request', [PrivacyController::class, 'eraseRequest'])
            ->middleware('throttle:10,1');
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
            Route::middleware(['throttle:b2b-onboarding'])
                ->withoutMiddleware([ThrottleRequests::class.':api'])
                ->group(function (): void {
                    Route::get('/onboarding', [OnboardingController::class, 'show']);
                    Route::patch('/onboarding', [OnboardingController::class, 'update']);
                    Route::post('/onboarding/documents', [OnboardingController::class, 'uploadDocument']);
                    Route::get('/onboarding/trust-questions', [OnboardingController::class, 'trustQuestions']);
                    Route::get('/onboarding/status', [OnboardingController::class, 'status']);
                });

            Route::post('/onboarding/submit', [OnboardingController::class, 'submit'])
                ->middleware([
                    'throttle:b2b-onboarding-submit',
                    'idempotent:b2b.onboarding.submit,60',
                ])
                ->withoutMiddleware([ThrottleRequests::class.':api']);

            Route::get('/company/profile', [CompanyProfileController::class, 'show']);
            Route::patch('/company/profile', [CompanyProfileController::class, 'update']);

            Route::get('/coverage-zone', [CoverageZoneController::class, 'show']);
            Route::put('/coverage-zone', [CoverageZoneController::class, 'update']);
            Route::delete('/coverage-zone', [CoverageZoneController::class, 'destroy']);

            Route::get('/dashboard', [B2BDashboardController::class, 'index']);
            Route::get('/wallet', [WalletController::class, 'show']);
            Route::post('/wallet/recharge', [WalletController::class, 'recharge'])
                ->middleware(['throttle:b2b-recharge', 'idempotent:b2b.wallet.recharge,1440']);
            Route::get('/wallet/recharge/{id}', [WalletController::class, 'rechargeStatus']);
            Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
            Route::get('/invoices', [WalletController::class, 'invoices']);

            Route::get('/marketplace/leads', [LeadMarketplaceController::class, 'index']);
            Route::get('/marketplace', [LeadMarketplaceController::class, 'index']);
            Route::post('/marketplace/leads/{id}/unlock', [LeadMarketplaceController::class, 'unlock'])
                ->middleware(['throttle:b2b-unlock', 'idempotent:b2b.marketplace.unlock,1440']);
            Route::post('/leads/{id}/unlock', [LeadMarketplaceController::class, 'unlock'])
                ->middleware(['throttle:b2b-unlock', 'idempotent:b2b.marketplace.unlock,1440']);

            Route::get('/crm/clients', [SmartCrmController::class, 'index']);
            Route::patch('/crm/clients/{id}', [CrmController::class, 'update']);
            Route::put('/crm/clients/{id}/status', [SmartCrmController::class, 'updateStatus']);
            Route::patch('/crm/clients/{id}/status', [SmartCrmController::class, 'updateStatus']);

            Route::get('/appointments', [AppointmentsController::class, 'index']);
            Route::post('/appointments', [AppointmentsController::class, 'store'])
                ->middleware('idempotent:b2b.appointments.create,60');

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
        Route::get('/wallet/pending-transfers', [AdminWalletController::class, 'pendingTransfers']);
        Route::post('/wallet/complete-transfer', [AdminWalletController::class, 'completeTransfer']);

        Route::get('/companies', [PartnersController::class, 'index']);
        Route::get('/partners', [PartnersController::class, 'index']);
        Route::get('/partners/{company}', [PartnersController::class, 'show']);
        Route::post('/partners/{company}/approve', [PartnerApprovalController::class, 'approve'])
            ->middleware('idempotent:admin.partners.approve,1440');
        Route::post('/partners/{company}/reject', [PartnerApprovalController::class, 'reject']);
        Route::post('/partners/{company}/suspend', [PartnersController::class, 'suspend']);
        Route::post('/partners/{company}/impersonate', [PartnersController::class, 'impersonate']);

        Route::post('/companies/{company}/approve', [CompanyVettingController::class, 'approve']);
        Route::post('/companies/{company}/reject', [PartnerApprovalController::class, 'reject']);
        Route::post('/companies/{company}/vetting/approve', [CompanyVettingController::class, 'approve']);

        Route::get('/advisor-bookings', [AdvisorBookingsController::class, 'index']);

        Route::get('/leads', [AdminLeadsController::class, 'index']);
        Route::get('/leads/{id}', [AdminLeadsController::class, 'show']);
        Route::patch('/leads/{id}/assign', [AdminLeadsController::class, 'assign']);
        Route::post('/leads/{id}/reroute', [AdminLeadsController::class, 'reroute']);

        Route::get('/settings', [AdminSettingsController::class, 'show']);
        Route::patch('/settings', [AdminSettingsController::class, 'update']);
        Route::get('/sectors', [AdminSettingsController::class, 'sectors']);
        Route::patch('/sectors/{id}', [AdminSettingsController::class, 'updateSector']);

        Route::get('/notifications', [AdminSettingsController::class, 'notifications']);
        Route::get('/search', [SearchController::class, 'index']);

        Route::get('/privacy/erasure-requests', [PrivacyErasureController::class, 'index']);
        Route::patch('/privacy/erasure-requests/{id}', [PrivacyErasureController::class, 'update']);

        Route::get('/webhooks/events', [AdminWebhooksController::class, 'events']);
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
            ->middleware('throttle:auth-otp-resend-cooldown');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [SessionController::class, 'logout']);
            Route::get('/me', [SessionController::class, 'me']);
        });
    });
});
