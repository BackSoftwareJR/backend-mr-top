<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use App\Enums\ConsentType;
use App\Enums\LeadStatus;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\ConsentLog;
use App\Models\Lead;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadMatchingTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVACY_HASH = '2215df58adb1f22c6ebabdd592c36ca5f58ab26c8c199d48fe617b0af61dcf00';

    private const TERMS_HASH = 'e4b21b8bb3965045dd22f39980a48d814a8da11b37c2b4c230808de9ea09a134';

    protected function setUp(): void
    {
        parent::setUp();

        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
            'wizard_schema' => [
                'id' => 'wenando-intake-v3',
                'title' => 'Analisi gratuita',
                'steps' => [],
            ],
            'matching_rules' => [
                'default_unlock_cost' => 15,
                'min_match_score_marketplace' => 50,
                'b2c_visible_min_score' => 50,
                'max_b2c_results' => 3,
            ],
        ]);

        Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Casa Serenità',
            'legal_name' => 'Casa Serenità S.r.l.',
            'city' => 'Milano',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'dynamic_attributes' => [
                'sector' => 'adi',
                'capacity' => 20,
                'nonSelfSufficient' => true,
                'nightStaff' => true,
            ],
        ]);

        ConsentLog::query()->create([
            'session_id' => 'test-session-002',
            'consent_type' => ConsentType::PrivacyPolicy,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
        ]);
        ConsentLog::query()->create([
            'session_id' => 'test-session-002',
            'consent_type' => ConsentType::TermsB2c,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => self::TERMS_HASH,
        ]);
    }

    public function test_lead_submission_triggers_matching_and_results(): void
    {
        $response = $this->postJson('/api/v1/b2c/leads', [
            'sector_slug' => 'senior-care',
            'payload' => [
                'autonomy' => 'parziale',
                'location' => ['label' => 'Milano (MI)', 'value' => 'milano-mi'],
                'budget' => ['min' => 1500, 'max' => 2500],
                'contact' => ['nome' => 'Mario', 'telefono' => '+39 333 123 4567'],
            ],
            'consent' => [
                'privacy_accepted' => true,
                'terms_accepted' => true,
            ],
            'consent_text_hash' => self::PRIVACY_HASH,
            'session_id' => 'test-session-002',
        ]);

        $response->assertCreated();
        $uuid = $response->json('data.lead.uuid');

        $lead = Lead::query()->where('uuid', $uuid)->first();
        $this->assertNotNull($lead);
        $this->assertSame(LeadStatus::Routed, $lead->status);
        $this->assertGreaterThan(0, $lead->leadMatches()->count());

        $this->getJson("/api/v1/b2c/leads/{$uuid}/results")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['diagnosis', 'matches', 'advisor']]);
    }

    public function test_wizard_config_endpoint(): void
    {
        $this->getJson('/api/v1/b2c/sectors/senior-care/wizard')
            ->assertOk()
            ->assertJsonPath('data.id', 'wenando-intake-v3');
    }
}
