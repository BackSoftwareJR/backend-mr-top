<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Payments\StripePaymentGateway as StripePaymentGatewayContract;
use App\Enums\AppointmentType;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Lead;
use App\Observers\PersonalAccessTokenObserver;
use App\Services\Payments\StripePaymentGateway;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Sentry\State\Scope;

use function Sentry\configureScope;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StripePaymentGatewayContract::class, function (): StripePaymentGateway {
            return new StripePaymentGateway(config('services.stripe.secret'));
        });
    }

    public function boot(): void
    {
        $this->registerSentryUserContext();

        PersonalAccessToken::observe(PersonalAccessTokenObserver::class);

        Route::bind('company', function (string $value): Company {
            return Company::query()->where('uuid', $value)->firstOrFail();
        });

        Route::bind('lead', function (string $value): Lead {
            $lead = Lead::findByExternalRef($value);

            if ($lead === null) {
                abort(404);
            }

            $userId = auth()->id();

            if ($userId === null || $lead->user_id !== $userId) {
                abort(403, 'Ricerca non autorizzata.');
            }

            return $lead;
        });

        Route::bind('advisorBooking', function (string $value): Appointment {
            $appointment = Appointment::query()->find($value);

            if ($appointment === null || $appointment->type !== AppointmentType::Advisor) {
                abort(404);
            }

            $userId = auth()->id();

            if ($userId === null || $appointment->user_id !== $userId) {
                abort(403, 'Prenotazione non autorizzata.');
            }

            return $appointment;
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

        RateLimiter::for('b2b-unlock', function (Request $request) {
            $user = $request->user();
            $companyId = $user?->companies()->value('companies.id');
            $key = $companyId ?? $user?->id ?? $request->ip();

            return Limit::perMinute(30)->by((string) $key);
        });

        RateLimiter::for('b2b-recharge', function (Request $request) {
            $user = $request->user();
            $companyId = $user?->companies()->value('companies.id');
            $key = $companyId ?? $user?->id ?? $request->ip();

            return Limit::perHour(10)->by((string) $key);
        });

        RateLimiter::for('b2c-lead-results', function (Request $request) {
            $uuid = (string) $request->route('uuid', '');

            return Limit::perMinute(60)->by($uuid.'|'.$request->ip());
        });

        RateLimiter::for('locations-autocomplete', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('b2b-onboarding', function (Request $request) {
            $user = $request->user();
            $companyId = $user?->companies()->value('companies.id');
            $key = $companyId ?? $user?->id ?? $request->ip();

            return Limit::perMinute(300)->by('b2b-onboarding:'.(string) $key);
        });

        RateLimiter::for('b2b-onboarding-submit', function (Request $request) {
            $user = $request->user();
            $companyId = $user?->companies()->value('companies.id');
            $key = $companyId ?? $user?->id ?? $request->ip();

            return Limit::perMinute(10)->by('b2b-onboarding-submit:'.(string) $key);
        });
    }

    private function registerSentryUserContext(): void
    {
        if (! config('sentry.dsn')) {
            return;
        }

        Event::listen(function (Authenticated $event): void {
            configureScope(static function (Scope $scope) use ($event): void {
                $scope->setUser(['id' => (string) $event->user->getAuthIdentifier()]);
            });
        });

        Event::listen(function (Logout $event): void {
            configureScope(static function (Scope $scope): void {
                $scope->removeUser();
            });
        });
    }
}
