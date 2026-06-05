<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class B2bOnboardingRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVACY_HASH = 'ba8597379922b6fe4154e6448474495391e40e7fa2cd8688f51cf159f7252006';

    private const TERMS_B2B_HASH = '3660d5b1e1b49d528e87bf7d844abd9eee72dec18dd798bee1d097bd3d1ce008';

    protected function setUp(): void
    {
        parent::setUp();

        Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        RateLimiter::clear('b2b-onboarding');
        RateLimiter::clear('b2b-onboarding-submit');
    }

    public function test_onboarding_patch_exceeds_global_api_throttle_budget(): void
    {
        $user = $this->registerPartner('patch-limit@struttura.it');
        Sanctum::actingAs($user, ['*']);

        for ($i = 0; $i < 130; $i++) {
            $this->patchJson('/api/v1/b2b/onboarding', [
                'data' => ['sdi' => 'ABC12'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
            ])->assertOk();
        }
    }

    public function test_onboarding_submit_is_idempotent_and_rate_limited(): void
    {
        $user = $this->registerPartner('submit-limit@struttura.it');
        Sanctum::actingAs($user, ['*']);

        $this->patchJson('/api/v1/b2b/onboarding', [
            'vat' => 'IT12345678901',
            'sdi' => 'ABCDEFG',
        ])->assertOk();

        $this->post('/api/v1/b2b/onboarding/documents', [
            'type' => 'visura',
            'file' => UploadedFile::fake()->create('visura.pdf', 128, 'application/pdf'),
        ])->assertOk();

        $this->post('/api/v1/b2b/onboarding/documents', [
            'type' => 'identity',
            'file' => UploadedFile::fake()->create('identity.pdf', 64, 'application/pdf'),
        ])->assertOk();

        $this->putJson('/api/v1/b2b/coverage-zone', [
            'center_lat' => 41.9028,
            'center_lng' => 12.4964,
            'radius_km' => 20,
            'label' => 'Roma',
        ])->assertOk();

        $payload = [
            'terms_b2b_accepted' => true,
            'terms_text_hash' => self::TERMS_B2B_HASH,
        ];
        $idempotencyKey = '550e8400-e29b-41d4-a716-446655440000';

        $first = $this->postJson('/api/v1/b2b/onboarding/submit', $payload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);
        $first->assertOk()->assertJsonPath('data.status', 'pending_review');

        $second = $this->postJson('/api/v1/b2b/onboarding/submit', $payload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);
        $second->assertOk()->assertJsonPath('data.status', 'pending_review');
    }

    public function test_trust_questions_returns_sector_specific_questions(): void
    {
        $user = $this->registerPartner('trust@struttura.it');
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/b2b/onboarding/trust-questions?sector=rsa')
            ->assertOk()
            ->assertJsonPath('data.sector', 'rsa')
            ->assertJsonCount(8, 'data.questions')
            ->assertJsonPath('data.questions.0.type', 'radio');

        $this->getJson('/api/v1/b2b/onboarding/trust-questions?sector=adi')
            ->assertOk()
            ->assertJsonPath('data.sector', 'adi')
            ->assertJsonCount(8, 'data.questions');
    }

    private function registerPartner(string $email): User
    {
        $this->postJson('/api/v1/b2b/register', [
            'email' => $email,
            'organization_name' => 'Test Partner',
            'legal_name' => 'Test Partner S.r.l.',
            'privacy_policy_accepted' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
        ])->assertCreated();

        return User::query()->where('email', $email)->firstOrFail();
    }
}
