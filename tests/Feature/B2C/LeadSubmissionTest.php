<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use App\Enums\ConsentType;
use App\Enums\LeadStatus;
use App\Models\ConsentLog;
use App\Models\Lead;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVACY_HASH = '2215df58adb1f22c6ebabdd592c36ca5f58ab26c8c199d48fe617b0af61dcf00';

    private const TERMS_HASH = 'e4b21b8bb3965045dd22f39980a48d814a8da11b37c2b4c230808de9ea09a134';

    protected function setUp(): void
    {
        parent::setUp();

        Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);
    }

    public function test_b2c_lead_submission_creates_lead_and_consent_log(): void
    {
        ConsentLog::query()->create([
            'session_id' => 'test-session-001',
            'consent_type' => ConsentType::PrivacyPolicy,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
        ]);
        ConsentLog::query()->create([
            'session_id' => 'test-session-001',
            'consent_type' => ConsentType::TermsB2c,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => self::TERMS_HASH,
        ]);

        $payload = [
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
                'marketing_accepted' => false,
            ],
            'consent_text_hash' => self::PRIVACY_HASH,
            'session_id' => 'test-session-001',
        ];

        $response = $this->postJson('/api/v1/b2c/leads', $payload);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lead.status', LeadStatus::Processing->value)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'lead' => ['uuid', 'public_ref', 'status'],
                    'job_id',
                ],
                'meta' => ['request_id'],
            ]);

        $this->assertDatabaseCount('leads', 1);
        $lead = Lead::query()->first();
        $this->assertNotNull($lead);
        $this->assertSame('LD-'.$lead->id, $lead->public_ref);
        $this->assertSame('Mario', $lead->contact_name);

        $this->assertDatabaseHas('consent_logs', [
            'lead_id' => $lead->id,
            'consent_type' => ConsentType::PrivacyPolicy->value,
            'consent_given' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
        ]);

        $consentLog = ConsentLog::query()->where('lead_id', $lead->id)->first();
        $this->assertNotNull($consentLog);
        $this->assertSame('b2c_lead_submission', $consentLog->metadata['source'] ?? null);
    }

    public function test_b2c_lead_submission_rejects_missing_privacy_consent(): void
    {
        $response = $this->postJson('/api/v1/b2c/leads', [
            'sector_slug' => 'senior-care',
            'payload' => [
                'autonomy' => 'parziale',
                'location' => ['label' => 'Milano (MI)', 'value' => 'milano-mi'],
                'budget' => ['min' => 1500, 'max' => 2500],
                'contact' => ['nome' => 'Mario', 'telefono' => '+39 333 123 4567'],
            ],
            'consent' => ['privacy_accepted' => false],
            'consent_text_hash' => self::PRIVACY_HASH,
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_b2b_routes_require_partner_role(): void
    {
        $response = $this->getJson('/api/v1/b2b/dashboard');

        $response->assertUnauthorized();
    }
}
