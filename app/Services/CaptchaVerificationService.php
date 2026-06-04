<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CaptchaVerificationService
{
    private const HCAPTCHA_VERIFY_URL = 'https://api.hcaptcha.com/siteverify';

    private const RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public function isRequired(): bool
    {
        return $this->provider() !== null;
    }

    public function provider(): ?string
    {
        $hcaptchaSecret = config('services.hcaptcha.secret');
        if (is_string($hcaptchaSecret) && $hcaptchaSecret !== '') {
            return 'hcaptcha';
        }

        $recaptchaSecret = config('services.recaptcha.secret');
        if (is_string($recaptchaSecret) && $recaptchaSecret !== '') {
            return 'recaptcha';
        }

        return null;
    }

    public function verify(string $token, ?string $remoteIp = null): bool
    {
        $provider = $this->provider();
        if ($provider === null) {
            return true;
        }

        $token = trim($token);
        if ($token === '') {
            return false;
        }

        $payload = [
            'secret' => $this->secretForProvider($provider),
            'response' => $token,
        ];

        if ($remoteIp !== null && $remoteIp !== '') {
            $payload['remoteip'] = $remoteIp;
        }

        $url = $provider === 'hcaptcha'
            ? self::HCAPTCHA_VERIFY_URL
            : self::RECAPTCHA_VERIFY_URL;

        $response = Http::asForm()
            ->timeout(5)
            ->post($url, $payload);

        if (! $response->successful()) {
            return false;
        }

        return (bool) $response->json('success', false);
    }

    private function secretForProvider(string $provider): string
    {
        return match ($provider) {
            'hcaptcha' => (string) config('services.hcaptcha.secret'),
            'recaptcha' => (string) config('services.recaptcha.secret'),
        };
    }
}
