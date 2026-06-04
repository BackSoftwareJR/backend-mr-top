<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\AuditAction;
use App\Enums\PaymentIntentStatus;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Enums\WebhookEventStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PaymentIntent;
use App\Models\Sector;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentIntentRechargeTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-webhook-secret-for-payments';

    private Company $company;

    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'wenando.wallet_instant_recharge' => false,
            'wenando.webhook_secret' => self::WEBHOOK_SECRET,
        ]);

        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Casa Serenità',
            'legal_name' => 'Casa Serenità S.r.l.',
            'city' => 'Milano',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'dynamic_attributes' => [],
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

    public function test_recharge_creates_pending_payment_intent_without_crediting_wallet(): void
    {
        Sanctum::actingAs($this->partner);

        $response = $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 100,
            'payment_method' => 'card',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.payment_intent.status', PaymentIntentStatus::Pending->value)
            ->assertJsonPath('data.wallet.balance_credits', 150)
            ->assertJsonStructure([
                'data' => [
                    'payment_intent_id',
                    'client_secret',
                ],
            ]);

        $this->assertDatabaseHas('payment_intents', [
            'company_id' => $this->company->id,
            'credits' => 100,
            'amount_cents' => 10000,
            'status' => PaymentIntentStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('wallets', [
            'company_id' => $this->company->id,
            'balance_credits' => 150,
        ]);

        $this->assertSame(0, Transaction::query()
            ->where('company_id', $this->company->id)
            ->where('type', 'recharge')
            ->count());
    }

    public function test_webhook_completed_adds_credits_transaction_and_audit_log(): void
    {
        Sanctum::actingAs($this->partner);

        $recharge = $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 50,
            'payment_method' => 'card',
        ])->assertOk();

        $paymentIntentId = $recharge->json('data.payment_intent_id');

        $webhook = $this->postJson('/api/v1/webhooks/payments/mock', [
            'payment_intent_id' => $paymentIntentId,
            'provider_ref' => 'evt_mock_001',
            'status' => 'completed',
        ], [
            'X-Wenando-Webhook-Secret' => self::WEBHOOK_SECRET,
        ]);

        $webhook->assertOk()
            ->assertJsonPath('data.payment_intent.status', PaymentIntentStatus::Completed->value);

        $this->assertDatabaseHas('wallets', [
            'company_id' => $this->company->id,
            'balance_credits' => 200,
        ]);

        $transaction = Transaction::query()
            ->where('company_id', $this->company->id)
            ->where('type', 'recharge')
            ->first();

        $this->assertNotNull($transaction);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::WalletRecharge->value,
            'subject_type' => Transaction::class,
            'subject_id' => $transaction->id,
        ]);

        $auditLog = AuditLog::query()
            ->forAction(AuditAction::WalletRecharge)
            ->where('subject_id', $transaction->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('webhook', $auditLog->metadata['source']);
        $this->assertSame($paymentIntentId, $auditLog->metadata['payment_intent_id']);

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'mock',
            'event_type' => 'payment.completed',
            'status' => WebhookEventStatus::Processed->value,
        ]);
    }

    public function test_recharge_status_endpoint_returns_intent_and_wallet(): void
    {
        Sanctum::actingAs($this->partner);

        $recharge = $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 25,
            'payment_method' => 'transfer',
        ])->assertOk();

        $paymentIntentId = $recharge->json('data.payment_intent_id');

        $status = $this->getJson('/api/v1/b2b/wallet/recharge/'.$paymentIntentId);

        $status->assertOk()
            ->assertJsonPath('data.payment_intent.status', PaymentIntentStatus::Pending->value)
            ->assertJsonPath('data.wallet.balance_credits', 150)
            ->assertJsonStructure([
                'data' => [
                    'bank_transfer' => ['iban', 'beneficiary', 'reference', 'amount', 'currency'],
                ],
            ]);

        $this->postJson('/api/v1/webhooks/payments/mock', [
            'payment_intent_id' => $paymentIntentId,
            'status' => 'completed',
        ], [
            'X-Wenando-Webhook-Secret' => self::WEBHOOK_SECRET,
        ])->assertOk();

        $this->getJson('/api/v1/b2b/wallet/recharge/'.$paymentIntentId)
            ->assertOk()
            ->assertJsonPath('data.payment_intent.status', PaymentIntentStatus::Completed->value)
            ->assertJsonPath('data.wallet.balance_credits', 175)
            ->assertJsonPath('data.transaction.status', 'completed');
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        $intent = PaymentIntent::query()->create([
            'public_ref' => 'PI-TEST-1',
            'company_id' => $this->company->id,
            'amount_cents' => 1000,
            'credits' => 10,
            'status' => PaymentIntentStatus::Pending,
            'client_secret' => 'pi_test_secret',
        ]);

        $this->postJson('/api/v1/webhooks/payments/mock', [
            'payment_intent_id' => $intent->public_ref,
            'status' => 'completed',
        ], [
            'X-Wenando-Webhook-Secret' => 'wrong-secret',
        ])->assertStatus(401);
    }

    public function test_instant_recharge_env_credits_immediately(): void
    {
        config(['wenando.wallet_instant_recharge' => true]);

        Sanctum::actingAs($this->partner);

        $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 20,
            'payment_method' => 'card',
        ])
            ->assertOk()
            ->assertJsonPath('data.wallet.balance_credits', 170)
            ->assertJsonPath('data.transaction.status', 'completed');

        $this->assertDatabaseMissing('payment_intents', [
            'company_id' => $this->company->id,
        ]);
    }

    public function test_duplicate_webhook_does_not_double_credit(): void
    {
        Sanctum::actingAs($this->partner);

        $paymentIntentId = $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 30,
            'payment_method' => 'card',
        ])->json('data.payment_intent_id');

        $headers = ['X-Wenando-Webhook-Secret' => self::WEBHOOK_SECRET];
        $payload = [
            'payment_intent_id' => $paymentIntentId,
            'status' => 'completed',
        ];

        $this->postJson('/api/v1/webhooks/payments/mock', $payload, $headers)->assertOk();
        $this->postJson('/api/v1/webhooks/payments/mock', $payload, $headers)->assertOk();

        $this->assertDatabaseHas('wallets', [
            'company_id' => $this->company->id,
            'balance_credits' => 180,
        ]);

        $this->assertSame(1, Transaction::query()
            ->where('company_id', $this->company->id)
            ->where('type', 'recharge')
            ->count());
    }
}
