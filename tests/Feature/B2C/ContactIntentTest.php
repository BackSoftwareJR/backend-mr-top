<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use App\Enums\ConsentType;
use App\Enums\LeadStatus;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\CompanyCoverageZone;
use App\Models\CompanyProfile;
use App\Models\ConsentLog;
use App\Models\Lead;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactIntentTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/b2c/search/contact-intent';

    private const PRIVACY_HASH = '2215df58adb1f22c6ebabdd592c36ca5f58ab26c8c199d48fe617b0af61dcf00';

    private const TERMS_HASH = 'e4b21b8bb3965045dd22f39980a48d814a8da11b37c2b4c230808de9ea09a134';

    private const LEAD_SHARING_HASH = '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('wizard-submit');

        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
            'matching_rules' => [
                'default_unlock_cost' => 15,
                'min_match_score_marketplace' => 50,
                'b2c_visible_min_score' => 50,
                'max_b2c_results' => 3,
            ],
        ]);

        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'RSA Milano Centro',
            'legal_name' => 'RSA Milano Centro S.r.l.',
            'city' => 'Milano',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'dynamic_attributes' => [
                'sector' => 'rsa',
                'capacity' => 20,
                'nonSelfSufficient' => true,
                'nightStaff' => true,
            ],
        ]);

        CompanyProfile::query()->create([
            'company_id' => $company->id,
            'display_name' => 'RSA Milano Centro',
            'service_type' => 'Residenza Sanitaria Assistenziale',
            'description' => 'Struttura residenziale con assistenza h24.',
            'location_label' => 'Milano, Zona Centro',
            'image_url' => 'https://example.com/rsa.jpg',
        ]);

        CompanyCoverageZone::query()->create([
            'company_id' => $company->id,
            'center_lat' => 45.4642,
            'center_lng' => 9.1900,
            'radius_km' => 25,
        ]);
    }

    public function test_contact_intent_requires_query_and_contact(): void
    {
        $this->postJson(self::ENDPOINT, [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_contact_intent_creates_lead_and_returns_geo_matched_structures(): void
    {
        $this->seedWizardConsents('explore-session-001');

        $response = $this->postJson(self::ENDPOINT, [
            'query' => 'rsa per mamma a Milano',
            'selections' => [
                'refinement_zone' => 'milano-mi',
                'refinement_care' => 'intensive',
                'refinement_budget' => 'mid',
            ],
            'refinementHistory' => [
                [
                    'questionId' => 'refinement_zone',
                    'answerId' => 'milano-mi',
                    'answerLabel' => 'Milano (MI)',
                ],
            ],
            'activePathId' => 'path_rsa',
            'interest_areas' => [
                [
                    'type' => 'circle',
                    'center_lat' => 45.4642,
                    'center_lng' => 9.1900,
                    'radius_km' => 15,
                    'label' => 'Milano (MI)',
                ],
            ],
            'contact' => [
                'nome' => 'Giulia',
                'telefono' => '+39 333 987 6543',
            ],
            'consent' => [
                'privacy_accepted' => true,
                'terms_accepted' => true,
                'lead_sharing_accepted' => true,
            ],
            'consent_text_hash' => self::PRIVACY_HASH,
            'terms_text_hash' => self::TERMS_HASH,
            'lead_sharing_text_hash' => self::LEAD_SHARING_HASH,
            'session_id' => 'explore-session-001',
            'explore_session_id' => 'local-explore-uuid',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lead.status', LeadStatus::Routed->value)
            ->assertJsonPath('data.match_count', 1)
            ->assertJsonPath('data.matches.0.name', 'RSA Milano Centro')
            ->assertJsonStructure([
                'data' => [
                    'lead' => ['uuid', 'public_ref', 'status'],
                    'matches' => [
                        ['id', 'name', 'compatibility', 'location'],
                    ],
                    'match_count',
                ],
            ]);

        $lead = Lead::query()->first();
        $this->assertNotNull($lead);
        $this->assertSame('Giulia', $lead->contact_name);
        $this->assertSame('explore_contact_intent', $lead->payload['source'] ?? null);
        $this->assertSame('path_rsa', $lead->payload['explore']['active_path_id'] ?? null);
    }

    public function test_contact_intent_rejects_missing_consents(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'query' => 'rsa milano',
            'selections' => ['refinement_zone' => 'milano'],
            'contact' => [
                'nome' => 'Giulia',
                'telefono' => '+39 333 987 6543',
            ],
            'consent' => [
                'privacy_accepted' => true,
                'terms_accepted' => true,
                'lead_sharing_accepted' => true,
            ],
            'consent_text_hash' => self::PRIVACY_HASH,
            'terms_text_hash' => self::TERMS_HASH,
            'lead_sharing_text_hash' => self::LEAD_SHARING_HASH,
            'session_id' => 'explore-session-no-consent',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('leads', 0);
    }

    private function seedWizardConsents(string $sessionId): void
    {
        ConsentLog::query()->create([
            'session_id' => $sessionId,
            'consent_type' => ConsentType::PrivacyPolicy,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
        ]);
        ConsentLog::query()->create([
            'session_id' => $sessionId,
            'consent_type' => ConsentType::TermsB2c,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => self::TERMS_HASH,
        ]);
        ConsentLog::query()->create([
            'session_id' => $sessionId,
            'consent_type' => ConsentType::LeadSharing,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => self::LEAD_SHARING_HASH,
        ]);
    }
}
