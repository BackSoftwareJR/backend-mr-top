<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\CrmStatus;
use App\Enums\LeadStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\ActivityFeed;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadMatch;
use App\Models\Sector;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class B2bActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    private Company $company;

    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['wenando.wallet_instant_recharge' => true]);

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
    }

    public function test_wallet_recharge_persists_activity_feed_entry(): void
    {
        Sanctum::actingAs($this->partner);

        $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 50,
            'payment_method' => 'card',
        ])->assertOk();

        $this->assertDatabaseHas('activity_feed', [
            'company_id' => $this->company->id,
            'type' => 'recharge',
            'text' => 'Ricarica wallet: 50 crediti',
        ]);
    }

    public function test_lead_unlock_persists_activity_feed_entry(): void
    {
        $leadMatch = $this->createMarketplaceLead('Maria Rossi');

        Sanctum::actingAs($this->partner);

        $this->postJson('/api/v1/b2b/marketplace/leads/ML-'.$leadMatch->id.'/unlock')
            ->assertOk();

        $this->assertDatabaseHas('activity_feed', [
            'company_id' => $this->company->id,
            'type' => 'unlock',
            'text' => 'Lead sbloccato: Maria Rossi',
        ]);
    }

    public function test_crm_status_change_persists_activity_feed_entry(): void
    {
        $leadMatch = $this->createUnlockedLead('Giuseppe Bianchi', CrmStatus::Nuovo);

        Sanctum::actingAs($this->partner);

        $this->patchJson(
            '/api/v1/b2b/crm/clients/'.$leadMatch->public_ref.'/status',
            ['stato' => 'Contattato'],
        )->assertOk();

        $this->assertDatabaseHas('activity_feed', [
            'company_id' => $this->company->id,
            'type' => 'status',
            'text' => 'Stato aggiornato: Giuseppe Bianchi → Contattato',
        ]);
    }

    public function test_dashboard_reads_persisted_activity_feed(): void
    {
        ActivityFeed::query()->create([
            'company_id' => $this->company->id,
            'type' => 'recharge',
            'text' => 'Ricarica wallet: 25 crediti',
            'metadata' => ['credits' => 25],
            'created_at' => now()->subHour(),
        ]);

        Transaction::query()->create([
            'company_id' => $this->company->id,
            'wallet_id' => $this->company->wallet->id,
            'public_ref' => 'TX-LEGACY-1',
            'type' => TransactionType::Recharge,
            'amount_cents' => 99900,
            'credits_delta' => 999,
            'status' => TransactionStatus::Completed,
            'description' => 'Legacy recharge not in feed',
            'completed_at' => now(),
        ]);

        Sanctum::actingAs($this->partner);

        $response = $this->getJson('/api/v1/b2b/dashboard')->assertOk();

        $activityFeed = $response->json('data.activity_feed');
        $this->assertCount(1, $activityFeed);
        $this->assertSame('recharge', $activityFeed[0]['type']);
        $this->assertSame('Ricarica wallet: 25 crediti', $activityFeed[0]['text']);
        $this->assertStringStartsWith('ACT-', $activityFeed[0]['id']);
    }

    public function test_dashboard_falls_back_to_computed_feed_when_empty(): void
    {
        $leadMatch = $this->createUnlockedLead('Anna Verdi', CrmStatus::Chiuso);

        Transaction::query()->create([
            'company_id' => $this->company->id,
            'wallet_id' => $this->company->wallet->id,
            'lead_match_id' => $leadMatch->id,
            'public_ref' => 'TX-UNLOCK-FB',
            'type' => TransactionType::LeadUnlock,
            'amount_cents' => 0,
            'credits_delta' => -15,
            'status' => TransactionStatus::Completed,
            'description' => 'Sblocco lead',
            'completed_at' => now()->subMinutes(5),
        ]);

        Sanctum::actingAs($this->partner);

        $response = $this->getJson('/api/v1/b2b/dashboard')->assertOk();

        $types = array_column($response->json('data.activity_feed'), 'type');
        $this->assertContains('unlock', $types);
    }

    private function createMarketplaceLead(string $contactName): LeadMatch
    {
        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-'.Str::upper(Str::random(4)),
            'sector_id' => $this->sector->id,
            'status' => LeadStatus::Routed,
            'payload' => [
                'autonomy' => 'parziale',
                'location' => ['label' => 'Milano (MI)', 'value' => 'milano-mi'],
            ],
            'contact_name' => $contactName,
            'contact_phone' => '+39 333 123 4567',
            'contact_email' => strtolower(str_replace(' ', '.', $contactName)).'@example.com',
            'location_label' => 'Milano (MI)',
            'budget_min' => 2000,
            'budget_max' => 2800,
            'need_summary' => 'Assistenza domiciliare h24',
        ]);

        return LeadMatch::query()->create([
            'lead_id' => $lead->id,
            'company_id' => $this->company->id,
            'match_score' => 95,
            'rank' => 1,
            'is_visible_to_consumer' => true,
            'is_in_marketplace' => true,
            'unlock_cost_credits' => 15,
            'unlocked_at' => null,
            'crm_status' => null,
        ]);
    }

    private function createUnlockedLead(string $contactName, CrmStatus $crmStatus): LeadMatch
    {
        $match = $this->createMarketplaceLead($contactName);
        $match->forceFill([
            'unlocked_at' => now()->subDay(),
            'crm_status' => $crmStatus,
        ])->save();

        return $match->fresh();
    }
}
