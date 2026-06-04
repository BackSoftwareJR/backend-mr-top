<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OtpPortal;
use App\Enums\UserType;
use App\Exceptions\ApiException;
use App\Mail\OtpMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class OtpAuthService
{
    public function __construct(
        private readonly UserAreaService $userAreaService,
    ) {}
    public const OTP_TTL_MINUTES = 10;

    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * @return array{email: string, expires_in_ms: int, dev_code?: string}
     */
    public function requestOtp(string $email, OtpPortal $portal): array
    {
        $email = Str::lower(trim($email));
        $this->assertPortalForEmail($email, $portal);

        $code = $this->generateCode();
        $now = now();

        OtpCode::query()
            ->where('email', $email)
            ->where('expires_at', '>', $now)
            ->delete();

        OtpCode::query()->create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'portal' => $portal,
            'expires_at' => $now->copy()->addMinutes(self::OTP_TTL_MINUTES),
            'last_sent_at' => $now,
            'attempts' => 0,
        ]);

        try {
            Mail::to($email)->send(new OtpMail($code, self::OTP_TTL_MINUTES));
        } catch (Throwable $e) {
            OtpCode::query()
                ->where('email', $email)
                ->where('expires_at', '>', $now)
                ->delete();

            Log::error('OTP mail send failed', [
                'email' => $email,
                'portal' => $portal->value,
                'mailer' => config('mail.default'),
                'error' => $e->getMessage(),
            ]);

            throw new ApiException(
                'OTP_MAIL_FAILED',
                'Impossibile inviare l\'email con il codice. Riprova tra poco o controlla la cartella spam.',
                503,
            );
        }

        $payload = [
            'email' => $email,
            'expires_in_ms' => self::OTP_TTL_MINUTES * 60 * 1000,
        ];

        if (app()->environment('local', 'testing')) {
            $payload['dev_code'] = $code;
        }

        return $payload;
    }

    /**
     * @return array{user: User, token: string, redirect_to: string}
     */
    public function verifyOtp(string $email, string $code, string $deviceName = 'wenando-api'): array
    {
        $email = Str::lower(trim($email));
        $otp = OtpCode::query()
            ->where('email', $email)
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if ($otp === null) {
            throw new ApiException(
                'OTP_NOT_FOUND',
                'Nessun codice attivo. Richiedine uno nuovo.',
                422,
            );
        }

        if ($otp->expires_at->isPast()) {
            throw new ApiException(
                'OTP_EXPIRED',
                'Codice scaduto. Richiedine uno nuovo.',
                422,
            );
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            throw new ApiException(
                'OTP_INVALID',
                'Codice non valido. Controlla e riprova.',
                422,
            );
        }

        $this->assertPortalForEmail($email, $otp->portal);

        $user = $this->resolveUser($email, $otp->portal);

        if ($otp->portal === OtpPortal::Consumer) {
            $user = $this->userAreaService->attachOrphanLeadsByEmail($user);
        }

        $user->forceFill(['last_login_at' => now(), 'email_verified_at' => now()])->save();

        $otp->delete();

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'redirect_to' => $this->redirectPath($user),
        ];
    }

    public function resendCooldownSeconds(string $email): int
    {
        $email = Str::lower(trim($email));
        $otp = OtpCode::query()
            ->where('email', $email)
            ->orderByDesc('id')
            ->first();

        if ($otp === null) {
            return 0;
        }

        $elapsed = now()->diffInSeconds($otp->last_sent_at);
        $remaining = self::RESEND_COOLDOWN_SECONDS - $elapsed;

        return max(0, (int) $remaining);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function assertPortalForEmail(string $email, OtpPortal $portal): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            if ($portal !== OtpPortal::Consumer) {
                throw new ApiException(
                    'WRONG_PORTAL',
                    'Account non trovato per questo portale.',
                    403,
                );
            }

            return;
        }

        $allowed = match ($portal) {
            OtpPortal::Consumer => $user->user_type === UserType::Consumer,
            OtpPortal::Partner => $user->user_type === UserType::B2b,
            OtpPortal::Admin => $user->user_type === UserType::Superadmin,
        };

        if (! $allowed) {
            throw new ApiException(
                'WRONG_PORTAL',
                'Questo indirizzo email non è valido per il portale selezionato.',
                403,
            );
        }
    }

    private function resolveUser(string $email, OtpPortal $portal): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            return $user;
        }

        if ($portal !== OtpPortal::Consumer) {
            throw new ApiException(
                'OTP_NOT_FOUND',
                'Nessun codice attivo. Richiedine uno nuovo.',
                422,
            );
        }

        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'email' => $email,
            'name' => Str::before($email, '@'),
            'user_type' => UserType::Consumer,
        ]);
    }

    private function redirectPath(User $user): string
    {
        return match ($user->user_type) {
            UserType::Consumer => '/user',
            UserType::B2b => $this->b2bRedirect($user),
            UserType::Superadmin => '/admin',
        };
    }

    private function b2bRedirect(User $user): string
    {
        $company = $user->companies()->first();
        if ($company === null) {
            return '/pro/onboarding';
        }

        return app(B2bOnboardingService::class)->redirectForVettingStatus($company->vetting_status);
    }
}
