<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OtpCaptchaTest extends TestCase
{
    use RefreshDatabase;

    private function captchaPayload(): array
    {
        return [
            'honeypot' => '',
            'human_confirmed' => true,
            'form_started_at' => now()->getTimestampMs() - 5000,
            'challenge_answer' => '1234',
            'expected_challenge' => '1234',
        ];
    }

    public function test_otp_request_skips_captcha_token_when_secret_unset(): void
    {
        Mail::fake();
        config(['services.hcaptcha.secret' => null, 'services.recaptcha.secret' => null]);

        $this->postJson('/api/v1/auth/otp/request', [
            'email' => 'consumer@example.com',
            'portal' => 'consumer',
            'captcha' => $this->captchaPayload(),
        ])->assertOk()
            ->assertJsonPath('success', true);

        Http::assertNothingSent();
    }

    public function test_otp_request_requires_captcha_token_when_hcaptcha_secret_set(): void
    {
        Mail::fake();
        config(['services.hcaptcha.secret' => 'test-hcaptcha-secret', 'services.recaptcha.secret' => null]);

        $this->postJson('/api/v1/auth/otp/request', [
            'email' => 'consumer@example.com',
            'portal' => 'consumer',
            'captcha' => $this->captchaPayload(),
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['captcha_token']]]);
    }

    public function test_otp_request_accepts_valid_hcaptcha_token(): void
    {
        Mail::fake();
        config(['services.hcaptcha.secret' => 'test-hcaptcha-secret', 'services.recaptcha.secret' => null]);

        Http::fake([
            'api.hcaptcha.com/siteverify' => Http::response(['success' => true]),
        ]);

        $this->postJson('/api/v1/auth/otp/request', [
            'email' => 'consumer@example.com',
            'portal' => 'consumer',
            'captcha_token' => 'valid-hcaptcha-token',
        ])->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.hcaptcha.com/siteverify'
                && $request['secret'] === 'test-hcaptcha-secret'
                && $request['response'] === 'valid-hcaptcha-token';
        });
    }

    public function test_otp_request_rejects_invalid_hcaptcha_token(): void
    {
        Mail::fake();
        config(['services.hcaptcha.secret' => 'test-hcaptcha-secret', 'services.recaptcha.secret' => null]);

        Http::fake([
            'api.hcaptcha.com/siteverify' => Http::response(['success' => false]),
        ]);

        $this->postJson('/api/v1/auth/otp/request', [
            'email' => 'consumer@example.com',
            'portal' => 'consumer',
            'captcha_token' => 'invalid-hcaptcha-token',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['captcha_token']]]);
    }

    public function test_otp_request_accepts_valid_recaptcha_token_when_recaptcha_secret_set(): void
    {
        Mail::fake();
        config(['services.hcaptcha.secret' => null, 'services.recaptcha.secret' => 'test-recaptcha-secret']);

        Http::fake([
            'www.google.com/recaptcha/api/siteverify' => Http::response(['success' => true]),
        ]);

        $this->postJson('/api/v1/auth/otp/request', [
            'email' => 'partner@example.com',
            'portal' => 'consumer',
            'captcha_token' => 'valid-recaptcha-token',
        ])->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
                && $request['secret'] === 'test-recaptcha-secret'
                && $request['response'] === 'valid-recaptcha-token';
        });
    }
}
