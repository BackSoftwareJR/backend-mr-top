<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\LeadStatus;
use App\Enums\UserType;
use App\Mail\OtpMail;
use App\Models\Lead;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

        Mail::assertSent(OtpMail::class, function (OtpMail $mail): bool {
            return $mail->hasTo('consumer@example.com');
        });
        Mail::assertNothingQueued();

        $code = $response->json('data.dev_code');

        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'email' => 'consumer@example.com',
            'code' => $code,
        ]);

        $verify->assertOk()
            ->assertJsonPath('data.user.user_type', 'consumer')
            ->assertJsonPath('data.user.name', 'consumer')
            ->assertJsonPath('data.user.phone', null)
            ->assertJsonPath('data.redirect_to', '/user')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email', 'name', 'phone', 'user_type']]]);

        $this->assertDatabaseHas('users', [
            'email' => 'consumer@example.com',
            'user_type' => UserType::Consumer->value,
        ]);
    }

    public function test_otp_request_sends_mail_for_unknown_consumer_email(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/otp/request', [
            'email' => 'new-user@example.com',
            'portal' => 'consumer',
            'captcha' => $this->captchaPayload(),
        ])->assertOk()
            ->assertJsonPath('data.email', 'new-user@example.com');

        Mail::assertSent(OtpMail::class, fn (OtpMail $mail): bool => $mail->hasTo('new-user@example.com'));
        $this->assertDatabaseHas('otp_codes', ['email' => 'new-user@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'new-user@example.com']);
    }

    public function test_verify_attaches_orphan_leads_by_contact_email(): void
    {
        Mail::fake();

        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-OTP-ATTACH',
            'sector_id' => $sector->id,
            'user_id' => null,
            'status' => LeadStatus::Processing,
            'payload' => ['contact' => ['nome' => 'Giulia', 'telefono' => '+39 340 111 2222']],
            'contact_name' => 'Giulia Bianchi',
            'contact_phone' => '+39 340 111 2222',
            'contact_email' => 'attach-me@example.com',
            'location_label' => 'Milano (MI)',
            'title' => 'Senior Care · Milano',
        ]);

        $request = $this->postJson('/api/v1/auth/otp/request', [
            'email' => 'attach-me@example.com',
            'portal' => 'consumer',
            'captcha' => $this->captchaPayload(),
        ])->assertOk();

        $code = $request->json('data.dev_code');

        $this->postJson('/api/v1/auth/otp/verify', [
            'email' => 'attach-me@example.com',
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('data.user.name', 'Giulia Bianchi')
            ->assertJsonPath('data.user.phone', '+39 340 111 2222');

        $userId = User::query()->where('email', 'attach-me@example.com')->value('id');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'user_id' => $userId,
        ]);
    }

    public function test_auth_me_and_logout(): void
    {
        $user = User::factory()->create([
            'user_type' => UserType::Consumer,
            'email' => 'me@example.com',
            'name' => 'Mario Rossi',
            'phone' => '+39 333 111 2222',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'me@example.com')
            ->assertJsonPath('data.user.name', 'Mario Rossi')
            ->assertJsonPath('data.user.phone', '+39 333 111 2222');

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

    public function test_admin_portal_accepts_superadmin_with_non_heuristic_email(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'jrovera05@gmail.com',
            'user_type' => UserType::Superadmin,
        ]);

        $this->postJson('/api/v1/auth/otp/request', [
            'email' => 'jrovera05@gmail.com',
            'portal' => 'admin',
            'captcha' => $this->captchaPayload(),
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertSent(OtpMail::class, fn (OtpMail $mail): bool => $mail->hasTo('jrovera05@gmail.com'));
    }
}
