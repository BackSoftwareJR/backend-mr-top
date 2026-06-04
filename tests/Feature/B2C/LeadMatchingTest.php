<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use App\Enums\ConsentType;
use App\Enums\LeadStatus;
use App\Enums\VettingStatus;
use App\Models\AdvisorProfile;
use App\Models\Company;
use App\Models\CompanyProfile;
use App\Models\ConsentLog;
use App\Models\Lead;
use App\Models\Sector;
use App\Services\LeadMatchingService;
use App\Support\ItalianLocationParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadMatchingTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVACY_HASH = '2215df58adb1f22c6ebabdd592c36ca5f58ab26c8c199d48fe617b0af61dcf00';

    private const TERMS_HASH = 'e4b21b8bb3965045dd22f39980a48d814a8da11b37c2b4c230808de9ea09a134';

    private const LEAD_SHARING_HASH = '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08';

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

        $company = Company::query()->create([
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

        CompanyProfile::query()->create([
            'company_id' => $company->id,
            'display_name' => 'Casa Serenità',
            'service_type' => 'Assistenza Domiciliare',
            'tagline' => 'Assistenza personalizzata con operatori qualificati',
            'description' => 'Assistenza personalizzata con operatori qualificati, flessibilità oraria.',
            'pros' => [
                'Operatori fissi e referente dedicato',
                'Orari su misura, anche weekend',
                'Supporto per ADI e detrazioni fiscali',
            ],
            'image_url' => 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=400&h=300&fit=crop&q=80',
            'location_label' => 'Milano, Zona Navigli',
            'contact_hint' => 'Richiedi un sopralluogo gratuito: rispondono entro 24 ore via telefono o WhatsApp.',
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
        AdvisorProfile::query()->create([
            'slug' => 'marco',
            'name' => 'Marco',
            'title' => 'Consulente pari',
            'bio' => 'Parla con Marco. Ha affrontato la stessa situazione con suo padre 2 anni fa.',
            'cta_label' => 'Prenota una chiamata gratuita (15 min)',
            'is_default' => true,
            'is_active' => true,
        ]);

        ConsentLog::query()->create([
            'session_id' => 'test-session-002',
            'consent_type' => ConsentType::LeadSharing,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => self::LEAD_SHARING_HASH,
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
                'lead_sharing_accepted' => true,
            ],
            'consent_text_hash' => self::PRIVACY_HASH,
            'terms_text_hash' => self::TERMS_HASH,
            'lead_sharing_text_hash' => self::LEAD_SHARING_HASH,
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
            ->assertJsonStructure(['data' => ['diagnosis', 'matches', 'advisor']])
            ->assertJsonPath('data.advisor.name', 'Marco')
            ->assertJsonPath('data.advisor.role', 'Consulente pari')
            ->assertJsonPath('data.advisor.cta_label', 'Prenota una chiamata gratuita (15 min)');
    }

    public function test_results_include_company_profile_fields(): void
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
                'lead_sharing_accepted' => true,
            ],
            'consent_text_hash' => self::PRIVACY_HASH,
            'terms_text_hash' => self::TERMS_HASH,
            'lead_sharing_text_hash' => self::LEAD_SHARING_HASH,
            'session_id' => 'test-session-002',
        ]);

        $uuid = $response->json('data.lead.uuid');

        $this->getJson("/api/v1/b2c/leads/{$uuid}/results")
            ->assertOk()
            ->assertJsonPath('data.matches.0.name', 'Casa Serenità')
            ->assertJsonPath('data.matches.0.type', 'Assistenza Domiciliare')
            ->assertJsonPath('data.matches.0.location', 'Milano, Zona Navigli')
            ->assertJsonPath('data.matches.0.tagline', 'Assistenza personalizzata con operatori qualificati')
            ->assertJsonPath('data.matches.0.image_url', 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?w=400&h=300&fit=crop&q=80')
            ->assertJsonPath('data.matches.0.contact_hint', 'Richiedi un sopralluogo gratuito: rispondono entro 24 ore via telefono o WhatsApp.')
            ->assertJsonCount(3, 'data.matches.0.pros');
    }

    public function test_wizard_config_endpoint(): void
    {
        $this->getJson('/api/v1/b2c/sectors/senior-care/wizard')
            ->assertOk()
            ->assertJsonPath('data.id', 'wenando-intake-v3');
    }

    public function test_geo_city_match_milano_scores_high(): void
    {
        $parser = app(ItalianLocationParser::class);
        $this->assertSame(100, $parser->bestGeoScore('Milano (MI)', ['Milano']));

        $lead = $this->createLeadForMatching('Milano (MI)');
        $matches = app(LeadMatchingService::class)->matchLead($lead);

        $this->assertCount(1, $matches);
        $this->assertGreaterThanOrEqual(70, $matches[0]->match_score);
    }

    public function test_geo_same_province_different_city_scores_80(): void
    {
        $parser = app(ItalianLocationParser::class);
        $this->assertSame(80, $parser->bestGeoScore('Sesto San Giovanni (MI)', ['Milano']));

        $lead = $this->createLeadForMatching('Sesto San Giovanni (MI)');
        $matches = app(LeadMatchingService::class)->matchLead($lead);

        $this->assertCount(1, $matches);
        $this->assertGreaterThanOrEqual(50, $matches[0]->match_score);
    }

    public function test_geo_same_region_scores_50(): void
    {
        $parser = app(ItalianLocationParser::class);
        $this->assertSame(50, $parser->bestGeoScore('Milano (MI)', ['Bergamo']));

        Company::query()->first()?->update(['city' => 'Bergamo']);

        $lead = $this->createLeadForMatching('Milano (MI)');
        $matches = app(LeadMatchingService::class)->matchLead($lead);

        $this->assertCount(1, $matches);
        $this->assertGreaterThanOrEqual(50, $matches[0]->match_score);
    }

    public function test_geo_different_region_excludes_partner(): void
    {
        $parser = app(ItalianLocationParser::class);
        $this->assertSame(0, $parser->bestGeoScore('Milano (MI)', ['Roma']));

        Company::query()->first()?->update(['city' => 'Roma']);

        $lead = $this->createLeadForMatching('Milano (MI)');
        $matches = app(LeadMatchingService::class)->matchLead($lead);

        $this->assertCount(0, $matches);
    }

    public function test_budget_full_overlap_scores_higher_than_partial_and_none(): void
    {
        $sector = Sector::query()->where('slug', 'senior-care')->firstOrFail();
        $baseAttrs = [
            'sector' => 'adi',
            'capacity' => 20,
            'nonSelfSufficient' => true,
            'nightStaff' => true,
        ];

        $fullOverlap = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Budget Full Overlap',
            'legal_name' => 'Budget Full Overlap S.r.l.',
            'city' => 'Milano',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'dynamic_attributes' => array_merge($baseAttrs, [
                'budget_min' => 1500,
                'budget_max' => 2500,
            ]),
        ]);

        $partialOverlap = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Budget Partial Overlap',
            'legal_name' => 'Budget Partial Overlap S.r.l.',
            'city' => 'Milano',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'dynamic_attributes' => array_merge($baseAttrs, [
                'budget_min' => 2000,
                'budget_max' => 3000,
            ]),
        ]);

        $noOverlap = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Budget No Overlap',
            'legal_name' => 'Budget No Overlap S.r.l.',
            'city' => 'Milano',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'dynamic_attributes' => array_merge($baseAttrs, [
                'budget_min' => 3000,
                'budget_max' => 4000,
            ]),
        ]);

        $lead = $this->createLeadForMatching('Milano (MI)');
        $matches = app(LeadMatchingService::class)->matchLead($lead);

        $scoresByCompany = [];
        foreach ($matches as $match) {
            $scoresByCompany[$match->company_id] = $match->match_score;
        }

        $this->assertSame(87, $scoresByCompany[$fullOverlap->id]);
        $this->assertSame(74, $scoresByCompany[$partialOverlap->id]);
        $this->assertSame(62, $scoresByCompany[$noOverlap->id]);
        $this->assertGreaterThan(
            $scoresByCompany[$partialOverlap->id],
            $scoresByCompany[$fullOverlap->id],
        );
        $this->assertGreaterThan(
            $scoresByCompany[$noOverlap->id],
            $scoresByCompany[$partialOverlap->id],
        );
    }

    public function test_budget_neutral_when_lead_has_no_budget(): void
    {
        $sector = Sector::query()->where('slug', 'senior-care')->firstOrFail();

        Company::query()->first()?->update([
            'dynamic_attributes' => [
                'sector' => 'adi',
                'capacity' => 20,
                'nonSelfSufficient' => true,
                'nightStaff' => true,
                'budget_min' => 3000,
                'budget_max' => 4000,
            ],
        ]);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-BUD-'.Str::upper(Str::random(4)),
            'sector_id' => $sector->id,
            'status' => LeadStatus::Processing,
            'payload' => [
                'autonomy' => 'parziale',
                'location' => ['label' => 'Milano (MI)', 'value' => 'test-budget'],
            ],
            'contact_name' => 'Budget Test',
            'contact_phone' => '+39 333 999 7777',
            'location_label' => 'Milano (MI)',
            'budget_min' => null,
            'budget_max' => null,
            'need_summary' => 'Assistenza domiciliare',
        ]);

        $matches = app(LeadMatchingService::class)->matchLead($lead);

        $this->assertCount(1, $matches);
        $this->assertSame(79, $matches[0]->match_score);
    }

    public function test_geo_uses_company_service_areas_when_present(): void
    {
        $parser = app(ItalianLocationParser::class);
        $this->assertSame(100, $parser->bestGeoScore('Milano (MI)', ['Roma', 'Milano (MI)']));

        Company::query()->first()?->update([
            'city' => 'Roma',
            'dynamic_attributes' => [
                'sector' => 'adi',
                'capacity' => 20,
                'nonSelfSufficient' => true,
                'nightStaff' => true,
                'service_areas' => ['Milano (MI)'],
            ],
        ]);

        $lead = $this->createLeadForMatching('Milano (MI)');
        $matches = app(LeadMatchingService::class)->matchLead($lead);

        $this->assertCount(1, $matches);
    }

    private function createLeadForMatching(string $locationLabel): Lead
    {
        $sector = Sector::query()->where('slug', 'senior-care')->firstOrFail();

        return Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-GEO-'.Str::upper(Str::random(4)),
            'sector_id' => $sector->id,
            'status' => LeadStatus::Processing,
            'payload' => [
                'autonomy' => 'parziale',
                'location' => ['label' => $locationLabel, 'value' => 'test-geo'],
            ],
            'contact_name' => 'Geo Test',
            'contact_phone' => '+39 333 999 8888',
            'location_label' => $locationLabel,
            'budget_min' => 1500,
            'budget_max' => 2500,
            'need_summary' => 'Assistenza domiciliare',
        ]);
    }
}
