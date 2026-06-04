<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\CrmStatus;
use App\Enums\LeadStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadMatch;
use App\Models\Notification;
use App\Models\Sector;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class B2bDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    private Company $company;

    private User $partner;

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
            'balance_credits' => 120,
            'total_spent_credits' => 45,
            'currency' => 'EUR',
        ]);

        $this->partner = User::factory()->create([
            'user_type' => UserType::B2b,
            'email' => 'partner@struttura.it',
            'password' => Hash::make('password123'),
        ]);

        $this->company->users()->attach($this->partner->id, ['role' => 'owner']);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/b2b/dashboard')->assertUnauthorized();
    }

    public function test_dashboard_returns_real_kpis_and_activity(): void
    {
        $availableLead = $this->createLeadMatch('Maria Rossi', unlocked: false);
        $unlockedLead = $this->createLeadMatch('Giuseppe Bianchi', unlocked: true, crmStatus: CrmStatus::Chiuso);
        $this->createLeadMatch('Anna Verdi', unlocked: true, crmStatus: CrmStatus::Contattato);

        $unlockedLead->forceFill(['unlocked_at' => now()->subDays(2)])->save();

        Transaction::query()->create([
            'company_id' => $this->company->id,
            'wallet_id' => $this->company->wallet->id,
            'lead_match_id' => $unlockedLead->id,
            'public_ref' => 'TX-UNLOCK-1',
            'type' => TransactionType::LeadUnlock,
            'amount_cents' => 0,
            'credits_delta' => -15,
            'status' => TransactionStatus::Completed,
            'description' => 'Sblocco lead ML-'.$unlockedLead->id,
            'completed_at' => now()->subDay(),
        ]);

        Transaction::query()->create([
            'company_id' => $this->company->id,
            'wallet_id' => $this->company->wallet->id,
            'public_ref' => 'TX-RECHARGE-1',
            'type' => TransactionType::Recharge,
            'amount_cents' => 5000,
            'credits_delta' => 50,
            'status' => TransactionStatus::Completed,
            'description' => 'Ricarica crediti',
            'completed_at' => now()->subHours(3),
        ]);

        Notification::query()->create([
            'notifiable_type' => Company::class,
            'notifiable_id' => $this->company->id,
            'type' => 'App\\Notifications\\PartnerLeadAvailable',
            'data' => ['title' => 'Nuovo lead', 'message' => 'Lead disponibile'],
            'read_at' => null,
        ]);

        Sanctum::actingAs($this->partner);

        $response = $this->getJson('/api/v1/b2b/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.wallet_balance_credits', 120)
            ->assertJsonPath('data.stats.leads_unlocked', 2)
            ->assertJsonPath('data.stats.marketplace_available', 1)
            ->assertJsonPath('data.stats.conversion_rate', 0.5)
            ->assertJsonPath('data.stats.monthly_spend', 15)
            ->assertJsonPath('data.notifications_unread', 1)
            ->assertJsonCount(7, 'data.leads_trend');

        $activityFeed = $response->json('data.activity_feed');
        $this->assertIsArray($activityFeed);
        $this->assertNotEmpty($activityFeed);

        $types = array_column($activityFeed, 'type');
        $this->assertContains('recharge', $types);
        $this->assertContains('unlock', $types);

        $trend = $response->json('data.leads_trend');
        $this->assertSame(1, $trend[0]['day']);
        $this->assertArrayHasKey('leads', $trend[0]);
        $this->assertArrayHasKey('date', $trend[0]);

        $this->assertSame(
            $availableLead->id,
            LeadMatch::query()->find($availableLead->id)?->id,
        );
    }

    public function test_dashboard_returns_zero_marketplace_for_unapproved_partner(): void
    {
        $this->company->forceFill([
            'vetting_status' => VettingStatus::PendingReview,
            'approved_at' => null,
        ])->save();

        $this->createLeadMatch('Lead Pending', unlocked: false);

        Sanctum::actingAs($this->partner);

        $response = $this->getJson('/api/v1/b2b/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.stats.marketplace_available', 0);
    }

    private function createLeadMatch(
        string $contactName,
        bool $unlocked = false,
        ?CrmStatus $crmStatus = null,
    ): LeadMatch {
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
            'unlocked_at' => $unlocked ? now() : null,
            'crm_status' => $crmStatus ?? ($unlocked ? CrmStatus::Nuovo : null),
        ]);
    }
}
