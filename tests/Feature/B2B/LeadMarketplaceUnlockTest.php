<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\CrmStatus;
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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadMarketplaceUnlockTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    private Company $company;

    private User $partner;

    private LeadMatch $leadMatch;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_marketplace_index_obfuscates_pii(): void
    {
        Sanctum::actingAs($this->partner);

        $response = $this->getJson('/api/v1/b2b/marketplace/leads');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.leads.0.id', 'ML-'.$this->leadMatch->id)
            ->assertJsonPath('data.leads.0.unlocked', false)
            ->assertJsonPath('data.leads.0.name', 'M*** R***')
            ->assertJsonMissing(['data.leads.0.phone' => '+39 333 123 4567']);
    }

    public function test_marketplace_unlock_deducts_credits_and_reveals_lead(): void
    {
        Sanctum::actingAs($this->partner);

        $response = $this->postJson('/api/v1/b2b/marketplace/leads/ML-'.$this->leadMatch->id.'/unlock');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lead.unlocked', true)
            ->assertJsonPath('data.lead.name', 'Maria Rossi')
            ->assertJsonPath('data.lead.phone', '+39 333 123 4567')
            ->assertJsonPath('data.wallet.balance_credits', 135)
            ->assertJsonPath('data.crm_client.stato', 'Nuovo')
            ->assertJsonPath('data.crm_client.marketplace_id', 'ML-'.$this->leadMatch->id);

        $this->assertDatabaseHas('lead_matches', [
            'id' => $this->leadMatch->id,
            'crm_status' => CrmStatus::Nuovo->value,
        ]);

        $this->assertNotNull(LeadMatch::query()->find($this->leadMatch->id)?->unlocked_at);
        $this->assertDatabaseHas('transactions', [
            'company_id' => $this->company->id,
            'type' => 'lead_unlock',
            'credits_delta' => -15,
        ]);
    }

    public function test_marketplace_unlock_returns_insufficient_credits(): void
    {
        Wallet::query()->where('company_id', $this->company->id)->update([
            'balance_credits' => 10,
        ]);

        Sanctum::actingAs($this->partner);

        $response = $this->postJson('/api/v1/b2b/leads/ML-'.$this->leadMatch->id.'/unlock');

        $response->assertStatus(402)
            ->assertJsonPath('error.code', 'INSUFFICIENT_CREDITS');

        $this->assertNull(LeadMatch::query()->find($this->leadMatch->id)?->unlocked_at);
    }

    public function test_b2b_login_returns_token_and_company(): void
    {
        $response = $this->postJson('/api/v1/b2b/auth/login', [
            'email' => 'partner@struttura.it',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.user_type', UserType::B2b->value)
            ->assertJsonPath('data.company.organization_name', 'Casa Serenità')
            ->assertJsonStructure(['data' => ['token', 'user', 'company']]);
    }
}
