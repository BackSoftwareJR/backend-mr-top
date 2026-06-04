<?php

declare(strict_types=1);

namespace Tests\Feature\Consent;

use App\Enums\ConsentType;
use App\Models\ConsentLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsentPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private const MARKETING_HASH = 'dee45a0f20974a87843a01602a48b0c4e90aa7fdbdf4c2c791049e507d3f24e5';

    private const ANALYTICS_HASH = 'b034d1ab94502c9f98e9a478bd25acd4bdf129794d3e0e9533edab92a3ddc6b1';

    public function test_patch_consents_me_appends_revocation_rows_for_authenticated_user(): void
    {
        $user = User::factory()->create(['user_type' => 'consumer']);

        ConsentLog::query()->create([
            'user_id' => $user->id,
            'consent_type' => ConsentType::Marketing,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => self::MARKETING_HASH,
        ]);

        ConsentLog::query()->create([
            'user_id' => $user->id,
            'consent_type' => ConsentType::AnalyticsCookies,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => self::ANALYTICS_HASH,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->withHeaders([
            'User-Agent' => 'WenandoPrefsTest/1.0',
        ])->patchJson('/api/v1/consents/me', [
            'preferences' => [
                [
                    'consent_type' => ConsentType::Marketing->value,
                    'consent_given' => false,
                    'consent_text_hash' => self::MARKETING_HASH,
                    'policy_version' => '1.0.0',
                ],
                [
                    'consent_type' => ConsentType::AnalyticsCookies->value,
                    'consent_given' => false,
                    'consent_text_hash' => self::ANALYTICS_HASH,
                    'policy_version' => '1.0.0',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.recorded')
            ->assertJsonPath('data.recorded.0.consent_given', false)
            ->assertJsonPath('data.recorded.1.consent_given', false);

        $this->assertDatabaseCount('consent_logs', 4);

        $latestMarketing = ConsentLog::query()
            ->forUser($user->id)
            ->ofType(ConsentType::Marketing)
            ->latestFirst()
            ->first();

        $latestAnalytics = ConsentLog::query()
            ->forUser($user->id)
            ->ofType(ConsentType::AnalyticsCookies)
            ->latestFirst()
            ->first();

        $this->assertFalse($latestMarketing->consent_given);
        $this->assertFalse($latestAnalytics->consent_given);
        $this->assertSame('user_preferences', $latestMarketing->metadata['source'] ?? null);
        $this->assertSame('user_preferences', $latestAnalytics->metadata['source'] ?? null);
        $this->assertSame('WenandoPrefsTest/1.0', $latestMarketing->user_agent);
    }

    public function test_patch_consents_me_requires_authentication(): void
    {
        $this->patchJson('/api/v1/consents/me', [
            'preferences' => [[
                'consent_type' => ConsentType::Marketing->value,
                'consent_given' => false,
                'consent_text_hash' => self::MARKETING_HASH,
                'policy_version' => '1.0.0',
            ]],
        ])->assertUnauthorized();
    }

    public function test_patch_consents_me_rejects_non_revocable_consent_types(): void
    {
        $user = User::factory()->create(['user_type' => 'consumer']);
        Sanctum::actingAs($user, ['*']);

        $this->patchJson('/api/v1/consents/me', [
            'preferences' => [[
                'consent_type' => ConsentType::PrivacyPolicy->value,
                'consent_given' => false,
                'consent_text_hash' => str_repeat('a', 64),
                'policy_version' => '1.0.0',
            ]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'preferences.0.consent_type',
                    ],
                ],
            ]);
    }

    public function test_get_consents_me_reflects_latest_preference_after_revocation(): void
    {
        $user = User::factory()->create(['user_type' => 'consumer']);

        ConsentLog::query()->create([
            'user_id' => $user->id,
            'consent_type' => ConsentType::AnalyticsCookies,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => self::ANALYTICS_HASH,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->patchJson('/api/v1/consents/me', [
            'preferences' => [[
                'consent_type' => ConsentType::AnalyticsCookies->value,
                'consent_given' => false,
                'consent_text_hash' => self::ANALYTICS_HASH,
                'policy_version' => '1.0.0',
            ]],
        ])->assertOk();

        $this->getJson('/api/v1/consents/me')
            ->assertOk()
            ->assertJsonPath(
                'data.latest_by_type.analytics_cookies.consent_given',
                false,
            );
    }
}
