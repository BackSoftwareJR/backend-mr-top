<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PaymentIntent;
use App\Models\Sector;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminWalletCompleteTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PaymentIntent $intent;

    protected function setUp(): void
    {
        parent::setUp();

        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Transfer Co',
            'legal_name' => 'Transfer Co S.r.l.',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'dynamic_attributes' => [],
        ]);

        Wallet::query()->create([
            'company_id' => $company->id,
            'balance_credits' => 50,
            'total_spent_credits' => 0,
            'currency' => 'EUR',
        ]);

        $this->intent = PaymentIntent::query()->create([
            'public_ref' => 'PI-ADMIN-TRANSFER-1',
            'company_id' => $company->id,
            'amount_cents' => 2000,
            'credits' => 20,
            'status' => PaymentIntentStatus::Pending,
            'payment_method' => PaymentMethod::Transfer,
            'provider' => 'bank_transfer',
        ]);

        $this->intent->provider_ref = 'WEN-'.$this->intent->id;
        $this->intent->save();

        $this->admin = User::factory()->create([
            'user_type' => UserType::Superadmin,
            'email' => 'admin@wenando.com',
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_superadmin_completes_transfer_by_wen_reference(): void
    {
        $this->postJson('/api/v1/admin/wallet/complete-transfer', [
            'payment_intent_id' => $this->intent->provider_ref,
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_intent.status', PaymentIntentStatus::Completed->value)
            ->assertJsonPath('data.wallet.balance_credits', 70);

        $transaction = Transaction::query()
            ->where('company_id', $this->intent->company_id)
            ->where('type', 'recharge')
            ->first();

        $this->assertNotNull($transaction);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::WalletRecharge->value,
            'subject_id' => $transaction->id,
        ]);

        $auditLog = AuditLog::query()
            ->forAction(AuditAction::WalletRecharge)
            ->where('subject_id', $transaction->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('admin_complete_transfer', $auditLog->metadata['source']);
    }

    public function test_complete_transfer_rejects_non_transfer_intent(): void
    {
        $this->intent->payment_method = PaymentMethod::Card;
        $this->intent->save();

        $this->postJson('/api/v1/admin/wallet/complete-transfer', [
            'payment_intent_id' => $this->intent->public_ref,
        ])->assertStatus(422);
    }
}
