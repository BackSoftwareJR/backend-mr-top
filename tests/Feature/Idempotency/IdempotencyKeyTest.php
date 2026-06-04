<?php

declare(strict_types=1);

namespace Tests\Feature\Idempotency;

use App\Enums\LeadStatus;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\IdempotencyKey;
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

class IdempotencyKeyTest extends TestCase
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

    public function test_duplicate_unlock_with_same_key_deducts_wallet_once(): void
    {
        Sanctum::actingAs($this->partner);

        $idempotencyKey = (string) Str::uuid();
        $url = '/api/v1/b2b/marketplace/leads/ML-'.$this->leadMatch->id.'/unlock';

        $first = $this->postJson($url, [], ['Idempotency-Key' => $idempotencyKey]);
        $second = $this->postJson($url, [], ['Idempotency-Key' => $idempotencyKey]);

        $first->assertOk()
            ->assertJsonPath('data.lead.unlocked', true)
            ->assertJsonPath('data.wallet.balance_credits', 135);

        $second->assertOk()
            ->assertJsonPath('data.lead.unlocked', true)
            ->assertJsonPath('data.wallet.balance_credits', 135);

        $this->assertSame($first->json('data.lead.name'), $second->json('data.lead.name'));

        $this->assertSame(1, Transaction::query()
            ->where('company_id', $this->company->id)
            ->where('type', 'lead_unlock')
            ->count());

        $this->assertDatabaseHas('wallets', [
            'company_id' => $this->company->id,
            'balance_credits' => 135,
        ]);

        $this->assertDatabaseHas('idempotency_keys', [
            'idempotency_key' => $idempotencyKey,
            'user_id' => $this->partner->id,
            'action' => 'b2b.marketplace.unlock',
        ]);
    }

    public function test_duplicate_wallet_recharge_with_same_key_credits_once(): void
    {
        config(['wenando.wallet_instant_recharge' => true]);

        Sanctum::actingAs($this->partner);

        $idempotencyKey = (string) Str::uuid();
        $payload = ['amount' => 100, 'payment_method' => 'card'];

        $first = $this->postJson('/api/v1/b2b/wallet/recharge', $payload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);
        $second = $this->postJson('/api/v1/b2b/wallet/recharge', $payload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $first->assertOk()
            ->assertJsonPath('data.wallet.balance_credits', 250);

        $second->assertOk()
            ->assertJsonPath('data.wallet.balance_credits', 250);

        $this->assertSame(1, Transaction::query()
            ->where('company_id', $this->company->id)
            ->where('type', 'recharge')
            ->count());

        $this->assertDatabaseHas('wallets', [
            'company_id' => $this->company->id,
            'balance_credits' => 250,
        ]);
    }

    public function test_wallet_recharge_idempotency_key_mismatch_returns_422(): void
    {
        config(['wenando.wallet_instant_recharge' => true]);

        Sanctum::actingAs($this->partner);

        $idempotencyKey = (string) Str::uuid();

        $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 100,
            'payment_method' => 'card',
        ], ['Idempotency-Key' => $idempotencyKey])->assertOk();

        $response = $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 200,
            'payment_method' => 'card',
        ], ['Idempotency-Key' => $idempotencyKey]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_MISMATCH');
    }

    public function test_unlock_idempotency_key_mismatch_returns_422(): void
    {
        Sanctum::actingAs($this->partner);

        $secondLead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-1002',
            'sector_id' => $this->sector->id,
            'status' => LeadStatus::Routed,
            'payload' => [
                'autonomy' => 'autonomo',
                'location' => ['label' => 'Roma (RM)', 'value' => 'roma-rm'],
            ],
            'contact_name' => 'Giuseppe Verdi',
            'contact_phone' => '+39 333 987 6543',
            'contact_email' => 'giuseppe@example.com',
            'location_label' => 'Roma (RM)',
            'budget_min' => 1800,
            'budget_max' => 2400,
            'need_summary' => 'Assistenza diurna',
        ]);

        $secondLeadMatch = LeadMatch::query()->create([
            'lead_id' => $secondLead->id,
            'company_id' => $this->company->id,
            'match_score' => 92,
            'rank' => 2,
            'is_visible_to_consumer' => true,
            'is_in_marketplace' => true,
            'unlock_cost_credits' => 15,
        ]);

        $idempotencyKey = (string) Str::uuid();
        $firstUrl = '/api/v1/b2b/marketplace/leads/ML-'.$this->leadMatch->id.'/unlock';
        $secondUrl = '/api/v1/b2b/marketplace/leads/ML-'.$secondLeadMatch->id.'/unlock';

        $this->postJson($firstUrl, [], ['Idempotency-Key' => $idempotencyKey])->assertOk();

        $response = $this->postJson($secondUrl, [], ['Idempotency-Key' => $idempotencyKey]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_MISMATCH');
    }

    public function test_invalid_idempotency_key_returns_validation_error(): void
    {
        Sanctum::actingAs($this->partner);

        $response = $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 100,
            'payment_method' => 'card',
        ], ['Idempotency-Key' => 'not-a-uuid']);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_unlock_without_idempotency_header_still_works(): void
    {
        Sanctum::actingAs($this->partner);

        $response = $this->postJson('/api/v1/b2b/marketplace/leads/ML-'.$this->leadMatch->id.'/unlock');

        $response->assertOk()
            ->assertJsonPath('data.wallet.balance_credits', 135);

        $this->assertSame(0, IdempotencyKey::query()->count());
    }

    public function test_duplicate_appointment_create_with_same_key_schedules_once(): void
    {
        Sanctum::actingAs($this->partner);

        $idempotencyKey = (string) Str::uuid();
        $clientId = 'CRM-'.$this->leadMatch->id;
        $payload = [
            'client_id' => $clientId,
            'date' => '2026-07-10',
            'time' => '10:30',
            'note' => 'Prima visita',
        ];

        $first = $this->postJson('/api/v1/b2b/appointments', $payload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);
        $second = $this->postJson('/api/v1/b2b/appointments', $payload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);

        $first->assertOk()
            ->assertJsonPath('data.appointment.date', '2026-07-10')
            ->assertJsonPath('data.appointment.time', '10:30');

        $second->assertOk()
            ->assertJsonPath('data.appointment.date', '2026-07-10')
            ->assertJsonPath('data.appointment.time', '10:30');

        $this->assertSame(
            $first->json('data.appointment.id'),
            $second->json('data.appointment.id'),
        );

        $this->assertSame(1, Appointment::query()
            ->where('company_id', $this->company->id)
            ->count());

        $this->assertDatabaseHas('idempotency_keys', [
            'idempotency_key' => $idempotencyKey,
            'user_id' => $this->partner->id,
            'action' => 'b2b.appointments.create',
        ]);
    }

    public function test_admin_partner_approve_is_idempotent(): void
    {
        $pendingCompany = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $this->sector->id,
            'organization_name' => 'Residenza Aurora',
            'legal_name' => 'Residenza Aurora S.r.l.',
            'vetting_status' => VettingStatus::PendingReview,
        ]);

        $admin = User::factory()->create([
            'user_type' => UserType::Superadmin,
        ]);

        Sanctum::actingAs($admin);

        $idempotencyKey = (string) Str::uuid();
        $url = '/api/v1/admin/partners/'.$pendingCompany->uuid.'/approve';

        $first = $this->postJson($url, [], ['Idempotency-Key' => $idempotencyKey]);
        $second = $this->postJson($url, [], ['Idempotency-Key' => $idempotencyKey]);

        $first->assertOk()
            ->assertJsonPath('data.company.vetting_status', VettingStatus::Approved->value);

        $second->assertOk()
            ->assertJsonPath('data.company.vetting_status', VettingStatus::Approved->value);

        $this->assertSame(
            $first->json('data.company.approved_at'),
            $second->json('data.company.approved_at'),
        );

        $this->assertDatabaseHas('idempotency_keys', [
            'idempotency_key' => $idempotencyKey,
            'user_id' => $admin->id,
            'action' => 'admin.partners.approve',
        ]);
    }
}
