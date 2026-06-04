<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\AuditAction;
use App\Enums\LeadStatus;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\AuditLog;
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

class MonetizationAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    private Company $company;

    private User $partner;

    private LeadMatch $leadMatch;

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

    public function test_wallet_recharge_creates_audit_log_with_metadata(): void
    {
        Sanctum::actingAs($this->partner);

        $idempotencyKey = (string) Str::uuid();

        $response = $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 100,
            'payment_method' => 'card',
        ], ['Idempotency-Key' => $idempotencyKey]);

        $response->assertOk();

        $transaction = Transaction::query()
            ->where('company_id', $this->company->id)
            ->where('type', 'recharge')
            ->first();

        $this->assertNotNull($transaction);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->partner->id,
            'action' => AuditAction::WalletRecharge->value,
            'subject_type' => Transaction::class,
            'subject_id' => $transaction->id,
        ]);

        $auditLog = AuditLog::query()
            ->forAction(AuditAction::WalletRecharge)
            ->where('user_id', $this->partner->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($this->company->id, $auditLog->metadata['company_id']);
        $this->assertSame(100, $auditLog->metadata['credits']);
        $this->assertSame(10000, $auditLog->metadata['amount_cents']);
        $this->assertSame('card', $auditLog->metadata['payment_method']);
        $this->assertSame($transaction->public_ref, $auditLog->metadata['transaction_public_ref']);
        $this->assertSame($idempotencyKey, $auditLog->metadata['idempotency_key']);
    }

    public function test_marketplace_unlock_creates_audit_log(): void
    {
        Sanctum::actingAs($this->partner);

        $idempotencyKey = (string) Str::uuid();

        $response = $this->postJson(
            '/api/v1/b2b/marketplace/leads/ML-'.$this->leadMatch->id.'/unlock',
            [],
            ['Idempotency-Key' => $idempotencyKey],
        );

        $response->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->partner->id,
            'action' => AuditAction::LeadUnlocked->value,
            'subject_type' => LeadMatch::class,
            'subject_id' => $this->leadMatch->id,
        ]);

        $auditLog = AuditLog::query()
            ->forAction(AuditAction::LeadUnlocked)
            ->where('user_id', $this->partner->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($this->company->id, $auditLog->metadata['company_id']);
        $this->assertSame($this->leadMatch->id, $auditLog->metadata['lead_match_id']);
        $this->assertSame(15, $auditLog->metadata['credits_debited']);
        $this->assertSame($idempotencyKey, $auditLog->metadata['idempotency_key']);
    }
}
