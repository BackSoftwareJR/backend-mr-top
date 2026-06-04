<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserType;
use App\Enums\VettingStatus;
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

class BankTransferRechargeTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-webhook-secret-for-payments';

    private const TEST_IBAN = 'IT60X0542811101000000123456';

    private const TEST_BENEFICIARY = 'Wenando S.r.l.';

    private Company $company;

    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'wenando.wallet_instant_recharge' => false,
            'wenando.webhook_secret' => self::WEBHOOK_SECRET,
            'wenando.bank_iban' => self::TEST_IBAN,
            'wenando.bank_beneficiary' => self::TEST_BENEFICIARY,
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
            'balance_credits' => 100,
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

    public function test_bank_transfer_recharge_creates_pending_intent_with_wen_reference(): void
    {
        Sanctum::actingAs($this->partner);

        $response = $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 40,
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.payment_intent.status', PaymentIntentStatus::Pending->value)
            ->assertJsonPath('data.wallet.balance_credits', 100)
            ->assertJsonPath('data.bank_transfer.iban', self::TEST_IBAN)
            ->assertJsonPath('data.bank_transfer.beneficiary', self::TEST_BENEFICIARY)
            ->assertJsonPath('data.bank_transfer.amount', 40)
            ->assertJsonPath('data.bank_transfer.currency', 'EUR');

        $intent = PaymentIntent::query()->where('company_id', $this->company->id)->first();

        $this->assertNotNull($intent);
        $this->assertSame(PaymentMethod::Transfer, $intent->payment_method);
        $this->assertSame('bank_transfer', $intent->provider);
        $this->assertSame('WEN-'.$intent->id, $intent->provider_ref);
        $this->assertSame('WEN-'.$intent->id, $response->json('data.bank_transfer.reference'));

        $this->assertDatabaseHas('wallets', [
            'company_id' => $this->company->id,
            'balance_credits' => 100,
        ]);

        $this->assertSame(0, Transaction::query()
            ->where('company_id', $this->company->id)
            ->where('type', 'recharge')
            ->count());
    }

    public function test_recharge_status_includes_bank_transfer_instructions(): void
    {
        Sanctum::actingAs($this->partner);

        $paymentIntentId = $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 25,
            'payment_method' => 'transfer',
        ])->json('data.payment_intent_id');

        $intent = PaymentIntent::query()->where('public_ref', $paymentIntentId)->first();
        $this->assertNotNull($intent);

        $this->getJson('/api/v1/b2b/wallet/recharge/'.$paymentIntentId)
            ->assertOk()
            ->assertJsonPath('data.bank_transfer.iban', self::TEST_IBAN)
            ->assertJsonPath('data.bank_transfer.reference', 'WEN-'.$intent->id)
            ->assertJsonPath('data.bank_transfer.amount', 25);
    }

    public function test_webhook_completed_by_wen_reference_credits_wallet(): void
    {
        Sanctum::actingAs($this->partner);

        $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 30,
            'payment_method' => 'transfer',
        ])->assertOk();

        $intent = PaymentIntent::query()->where('company_id', $this->company->id)->first();
        $this->assertNotNull($intent);

        $this->postJson('/api/v1/webhooks/payments/mock', [
            'payment_intent_id' => $intent->provider_ref,
            'status' => 'completed',
        ], [
            'X-Wenando-Webhook-Secret' => self::WEBHOOK_SECRET,
        ])->assertOk()
            ->assertJsonPath('data.payment_intent.status', PaymentIntentStatus::Completed->value);

        $this->assertDatabaseHas('wallets', [
            'company_id' => $this->company->id,
            'balance_credits' => 130,
        ]);
    }
}
