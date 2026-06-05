<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\ConsentType;
use App\Enums\VettingStatus;
use App\Models\ConsentLog;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class B2bOnboardingConsentTest extends TestCase
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
    }

    public function test_onboarding_submit_records_terms_b2b_consent_for_authenticated_partner(): void
    {
        $register = $this->postJson('/api/v1/b2b/register', [
            'email' => 'consent@struttura.it',
            'organization_name' => 'Consent Casa',
            'legal_name' => 'Consent Casa S.r.l.',
            'privacy_policy_accepted' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
        ]);

        $register->assertCreated();
        $user = User::query()->where('email', 'consent@struttura.it')->firstOrFail();
        Sanctum::actingAs($user, ['*']);

        $this->putJson('/api/v1/b2b/coverage-zone', [
            'center_lat' => 41.9028,
            'center_lng' => 12.4964,
            'radius_km' => 20,
            'label' => 'Roma',
        ])->assertOk();

        $response = $this->withHeaders([
            'User-Agent' => 'WenandoB2BTest/1.0',
        ])->postJson('/api/v1/b2b/onboarding/submit', [
            'terms_b2b_accepted' => true,
            'terms_text_hash' => self::TERMS_B2B_HASH,
            'policy_version' => '1.0.0',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', VettingStatus::PendingReview->value);

        $this->assertDatabaseHas('consent_logs', [
            'user_id' => $user->id,
            'consent_type' => ConsentType::TermsB2b->value,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => self::TERMS_B2B_HASH,
            'user_agent' => 'WenandoB2BTest/1.0',
        ]);

        $log = ConsentLog::query()
            ->where('user_id', $user->id)
            ->where('consent_type', ConsentType::TermsB2b)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('b2b_onboarding_submit', $log->metadata['source'] ?? null);
        $this->assertNotNull($log->ip_address);
    }

    public function test_onboarding_submit_requires_terms_b2b_acceptance(): void
    {
        $this->postJson('/api/v1/b2b/register', [
            'email' => 'noterms@struttura.it',
            'organization_name' => 'No Terms',
            'legal_name' => 'No Terms S.r.l.',
            'privacy_policy_accepted' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
        ])->assertCreated();

        $user = User::query()->where('email', 'noterms@struttura.it')->firstOrFail();
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/b2b/onboarding/submit')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'terms_b2b_accepted',
                        'terms_text_hash',
                    ],
                ],
            ]);

        $this->assertDatabaseCount('consent_logs', 1);
        $this->assertDatabaseHas('consent_logs', [
            'user_id' => $user->id,
            'consent_type' => ConsentType::PrivacyPolicy->value,
        ]);
        $this->assertDatabaseMissing('consent_logs', [
            'user_id' => $user->id,
            'consent_type' => ConsentType::TermsB2b->value,
        ]);
    }
}
