<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OtpAuthTest extends TestCase
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

    public function test_otp_request_and_verify_for_consumer(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/auth/otp/request', [
            'email' => 'consumer@example.com',
            'portal' => 'consumer',
            'captcha' => $this->captchaPayload(),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['email', 'expires_in_ms', 'dev_code']]);

        $code = $response->json('data.dev_code');

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'email' => 'consumer@example.com',
            'code' => $code,
        ]);

        $verify->assertOk()
            ->assertJsonPath('data.user.user_type', 'consumer')
            ->assertJsonPath('data.redirect_to', '/user')
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseHas('users', [
            'email' => 'consumer@example.com',
            'user_type' => UserType::Consumer->value,
        ]);
    }

    public function test_auth_me_and_logout(): void
    {
        $user = User::factory()->create([
            'user_type' => UserType::Consumer,
            'email' => 'me@example.com',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'me@example.com');

        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.success', true);
    }

    public function test_otp_rejects_wrong_portal(): void
    {
        User::factory()->create([
            'email' => 'partner@struttura.it',
            'user_type' => UserType::B2b,
        ]);

        $this->postJson('/api/v1/auth/otp/request', [
            'email' => 'partner@struttura.it',
            'portal' => 'consumer',
            'captcha' => $this->captchaPayload(),
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'WRONG_PORTAL');
    }
}
