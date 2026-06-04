<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use App\Enums\ConsentType;
use App\Models\ConsentLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentStoreTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVACY_HASH = '2215df58adb1f22c6ebabdd592c36ca5f58ab26c8c199d48fe617b0af61dcf00';

    private const TERMS_HASH = 'e4b21b8bb3965045dd22f39980a48d814a8da11b37c2b4c230808de9ea09a134';

    private const LEAD_SHARING_HASH = '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08';

    private const ANALYTICS_COOKIES_HASH = 'b034d1ab94502c9f98e9a478bd25acd4bdf129794d3e0e9533edab92a3ddc6b1';

    public function test_post_consents_records_wizard_privacy_acceptance_with_audit_fields(): void
    {
        $response = $this->withHeaders([
            'User-Agent' => 'WenandoTest/1.0',
        ])->postJson('/api/v1/consents', [
            'consents' => [[
                'consent_type' => ConsentType::PrivacyPolicy->value,
                'policy_version' => '1.0.0',
                'consent_given' => true,
                'consent_text_hash' => self::PRIVACY_HASH,
                'session_id' => 'wizard-session-abc',
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.recorded.0.consent_type', ConsentType::PrivacyPolicy->value)
            ->assertJsonPath('data.recorded.0.policy_version', '1.0.0')
            ->assertJsonPath('data.recorded.0.consent_given', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'recorded' => [
                        ['id', 'consent_type', 'policy_version', 'consent_given', 'created_at'],
                    ],
                ],
                'meta' => ['request_id'],
            ]);

        $this->assertDatabaseHas('consent_logs', [
            'consent_type' => ConsentType::PrivacyPolicy->value,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
            'session_id' => 'wizard-session-abc',
            'user_agent' => 'WenandoTest/1.0',
        ]);

        $log = ConsentLog::query()->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->created_at);
        $this->assertNull($log->user_id);
        $this->assertNull($log->lead_id);
    }

    public function test_post_consents_records_multiple_wizard_consents_in_one_request(): void
    {
        $response = $this->postJson('/api/v1/consents', [
            'consents' => [
                [
                    'consent_type' => ConsentType::PrivacyPolicy->value,
                    'policy_version' => '1.0.0',
                    'consent_given' => true,
                    'consent_text_hash' => self::PRIVACY_HASH,
                    'session_id' => 'wizard-session-xyz',
                ],
                [
                    'consent_type' => ConsentType::TermsB2c->value,
                    'policy_version' => '1.0.0',
                    'consent_given' => true,
                    'consent_text_hash' => self::TERMS_HASH,
                    'session_id' => 'wizard-session-xyz',
                ],
                [
                    'consent_type' => ConsentType::LeadSharing->value,
                    'policy_version' => '1.0.0',
                    'consent_given' => true,
                    'consent_text_hash' => self::LEAD_SHARING_HASH,
                    'session_id' => 'wizard-session-xyz',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonCount(3, 'data.recorded');

        $this->assertDatabaseCount('consent_logs', 3);
        $this->assertDatabaseHas('consent_logs', [
            'consent_type' => ConsentType::TermsB2c->value,
            'consent_text_hash' => self::TERMS_HASH,
            'session_id' => 'wizard-session-xyz',
        ]);
        $this->assertDatabaseHas('consent_logs', [
            'consent_type' => ConsentType::LeadSharing->value,
            'consent_text_hash' => self::LEAD_SHARING_HASH,
            'session_id' => 'wizard-session-xyz',
        ]);
    }

    public function test_post_consents_associates_authenticated_user_when_present(): void
    {
        $user = User::factory()->create([
            'user_type' => 'consumer',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/consents', [
            'consents' => [[
                'consent_type' => ConsentType::PrivacyPolicy->value,
                'policy_version' => '1.0.0',
                'consent_given' => true,
                'consent_text_hash' => self::PRIVACY_HASH,
            ]],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('consent_logs', [
            'user_id' => $user->id,
            'consent_type' => ConsentType::PrivacyPolicy->value,
        ]);
    }

    public function test_post_consents_records_analytics_cookies_acceptance(): void
    {
        $response = $this->postJson('/api/v1/consents', [
            'consents' => [[
                'consent_type' => ConsentType::AnalyticsCookies->value,
                'policy_version' => '1.0.0',
                'consent_given' => true,
                'consent_text_hash' => self::ANALYTICS_COOKIES_HASH,
                'session_id' => 'cookie-banner-session',
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.recorded.0.consent_type', ConsentType::AnalyticsCookies->value)
            ->assertJsonPath('data.recorded.0.consent_given', true);

        $this->assertDatabaseHas('consent_logs', [
            'consent_type' => ConsentType::AnalyticsCookies->value,
            'consent_given' => true,
            'consent_text_hash' => self::ANALYTICS_COOKIES_HASH,
            'session_id' => 'cookie-banner-session',
        ]);
    }

    public function test_post_consents_records_analytics_cookies_rejection(): void
    {
        $response = $this->postJson('/api/v1/consents', [
            'consents' => [[
                'consent_type' => ConsentType::AnalyticsCookies->value,
                'policy_version' => '1.0.0',
                'consent_given' => false,
                'consent_text_hash' => self::ANALYTICS_COOKIES_HASH,
                'session_id' => 'cookie-banner-session-reject',
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.recorded.0.consent_given', false);

        $this->assertDatabaseHas('consent_logs', [
            'consent_type' => ConsentType::AnalyticsCookies->value,
            'consent_given' => false,
            'session_id' => 'cookie-banner-session-reject',
        ]);
    }
}
