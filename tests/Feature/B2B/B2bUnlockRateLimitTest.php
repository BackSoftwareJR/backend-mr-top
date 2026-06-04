<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\LeadStatus;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadMatch;
use App\Models\Sector;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class B2bUnlockRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_REQUEST_ID = '01JTESTB2BUNLOCKRATELIMIT';

    private Sector $sector;

    private Company $company;

    private User $partner;

    private LeadMatch $leadMatch;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('b2b-unlock');

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $this->sector->id,
            'organization_name' => 'Casa Serenità',
            'legal_name' => 'Casa Serenità S.r.l.',
            'city' => 'Milano',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'dynamic_attributes' => ['service_areas' => ['Milano']],
        ]);

        Wallet::query()->create([
            'company_id' => $this->company->id,
            'balance_credits' => 150,
            'total_spent_credits' => 0,
            'currency' => 'EUR',
        ]);

        $this->partner = User::factory()->create([
            'user_type' => UserType::B2b,
            'email' => 'partner@struttura.it',
            'password' => Hash::make('password123'),
        ]);

        $this->company->users()->attach($this->partner->id, ['role' => 'owner']);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-1001',
            'sector_id' => $this->sector->id,
            'status' => LeadStatus::Routed,
            'payload' => [
                'autonomy' => 'parziale',
                'location' => ['label' => 'Milano (MI)', 'value' => 'milano-mi'],
            ],
            'contact_name' => 'Maria Rossi',
            'contact_phone' => '+39 333 123 4567',
            'contact_email' => 'maria@example.com',
            'location_label' => 'Milano (MI)',
            'budget_min' => 2000,
            'budget_max' => 2800,
            'need_summary' => 'Assistenza domiciliare h24',
        ]);

        $this->leadMatch = LeadMatch::query()->create([
            'lead_id' => $lead->id,
            'company_id' => $this->company->id,
            'match_score' => 98,
            'rank' => 1,
            'is_visible_to_consumer' => true,
            'is_in_marketplace' => true,
            'unlock_cost_credits' => 15,
        ]);
    }

    public function test_marketplace_unlock_returns_rate_limited_on_31st_request_in_window(): void
    {
        Sanctum::actingAs($this->partner);

        $url = '/api/v1/b2b/marketplace/leads/ML-'.$this->leadMatch->id.'/unlock';
        $headers = ['X-Request-Id' => self::CLIENT_REQUEST_ID];

        for ($i = 0; $i < 30; $i++) {
            $this->assertNotSame(429, $this->postJson($url, [], $headers)->status());
        }

        $response = $this->postJson($url, [], $headers);

        $response->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'RATE_LIMITED')
            ->assertJsonPath('trace_id', self::CLIENT_REQUEST_ID)
            ->assertJsonPath('request_id', self::CLIENT_REQUEST_ID)
            ->assertHeader('X-Request-Id', self::CLIENT_REQUEST_ID);

        $retryAfter = $response->headers->get('Retry-After');
        $this->assertNotNull($retryAfter);
        $this->assertGreaterThan(0, (int) $retryAfter);
    }

    public function test_legacy_unlock_route_shares_b2b_unlock_rate_limit(): void
    {
        Sanctum::actingAs($this->partner);

        $marketplaceUrl = '/api/v1/b2b/marketplace/leads/ML-'.$this->leadMatch->id.'/unlock';
        $legacyUrl = '/api/v1/b2b/leads/ML-'.$this->leadMatch->id.'/unlock';
        $headers = ['X-Request-Id' => self::CLIENT_REQUEST_ID];

        for ($i = 0; $i < 30; $i++) {
            $this->assertNotSame(429, $this->postJson($marketplaceUrl, [], $headers)->status());
        }

        $response = $this->postJson($legacyUrl, [], $headers);

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED')
            ->assertHeader('Retry-After');
    }
}
