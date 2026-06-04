<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OtpAuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_REQUEST_ID = '01JTESTOTPAUTHRATELIMIT';

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

    private function otpRequestPayload(string $email = 'consumer@example.com'): array
    {
        return [
            'email' => $email,
            'portal' => 'consumer',
            'captcha' => $this->captchaPayload(),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('auth-otp-request');
        RateLimiter::clear('auth-otp-resend-cooldown');
        RateLimiter::clear('auth-otp-verify');
    }

    public function test_otp_request_allows_ten_requests_per_fifteen_minutes(): void
    {
        $headers = ['X-Request-Id' => self::CLIENT_REQUEST_ID];

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/otp/request', $this->otpRequestPayload(), $headers)
                ->assertOk();
        }

        $response = $this->postJson('/api/v1/auth/otp/request', $this->otpRequestPayload(), $headers);

        $response->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'RATE_LIMITED');

        $retryAfter = $response->headers->get('Retry-After');
        $this->assertNotNull($retryAfter);
        $this->assertGreaterThan(0, (int) $retryAfter);
    }

    public function test_resend_cooldown_does_not_consume_otp_request_budget(): void
    {
        $headers = ['X-Request-Id' => self::CLIENT_REQUEST_ID];
        $email = 'cooldown@example.com';

        $this->postJson('/api/v1/auth/otp/request', $this->otpRequestPayload($email), $headers)
            ->assertOk();

        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/v1/auth/resend-cooldown?email='.urlencode($email), $headers)
                ->assertOk();
        }

        $this->postJson('/api/v1/auth/otp/request', $this->otpRequestPayload($email), $headers)
            ->assertOk();
    }

    public function test_otp_verify_has_separate_rate_limit_from_request(): void
    {
        $headers = ['X-Request-Id' => self::CLIENT_REQUEST_ID];
        $email = 'verify-limit@example.com';

        $this->postJson('/api/v1/auth/otp/request', $this->otpRequestPayload($email), $headers)
            ->assertOk();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', [
                'email' => $email,
                'code' => '000000',
            ], $headers)->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => $email,
            'code' => '000000',
        ], $headers)
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED');

        $this->postJson('/api/v1/auth/otp/request', $this->otpRequestPayload($email), $headers)
            ->assertOk()
            ->assertJsonPath('data.email', $email);
    }
}
