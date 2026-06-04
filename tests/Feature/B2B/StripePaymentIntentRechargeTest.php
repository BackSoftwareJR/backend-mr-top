<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Contracts\Payments\StripePaymentGateway;
use App\Enums\PaymentIntentStatus;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Enums\WebhookEventStatus;
use App\Models\Company;
use App\Models\PaymentIntent;
use App\Models\Sector;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Payments\FakeStripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StripePaymentIntentRechargeTest extends TestCase
{
    use RefreshDatabase;

    private const STRIPE_WEBHOOK_SECRET = 'whsec_test_stripe_webhook_secret';

    private Company $company;

    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        FakeStripePaymentGateway::reset();
        $this->app->instance(StripePaymentGateway::class, new FakeStripePaymentGateway);

        config([
            'wenando.wallet_instant_recharge' => false,
            'services.stripe.secret' => 'sk_test_fake',
            'services.stripe.webhook_secret' => self::STRIPE_WEBHOOK_SECRET,
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

    public function test_recharge_uses_stripe_client_secret_when_gateway_configured(): void
    {
        Sanctum::actingAs($this->partner);

        $response = $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 40,
            'payment_method' => 'card',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.client_secret', FakeStripePaymentGateway::$clientSecret);

        $paymentIntentId = $response->json('data.payment_intent_id');

        $this->assertDatabaseHas('payment_intents', [
            'public_ref' => $paymentIntentId,
            'provider' => 'stripe',
            'provider_ref' => FakeStripePaymentGateway::$paymentIntentId,
            'client_secret' => FakeStripePaymentGateway::$clientSecret,
        ]);

        $this->assertNotNull(FakeStripePaymentGateway::$lastCreate);
        $this->assertSame(4000, FakeStripePaymentGateway::$lastCreate['amount_cents']);
        $this->assertSame((string) $this->company->id, FakeStripePaymentGateway::$lastCreate['metadata']['company_id']);
        $this->assertSame($paymentIntentId, FakeStripePaymentGateway::$lastCreate['metadata']['wenando_payment_intent_ref']);
    }

    public function test_stripe_webhook_payment_intent_succeeded_completes_recharge(): void
    {
        Sanctum::actingAs($this->partner);

        $recharge = $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 25,
            'payment_method' => 'card',
        ])->assertOk();

        $paymentIntentId = $recharge->json('data.payment_intent_id');

        $payload = json_encode([
            'id' => 'evt_test_stripe_1',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => FakeStripePaymentGateway::$paymentIntentId,
                    'object' => 'payment_intent',
                    'metadata' => [
                        'company_id' => (string) $this->company->id,
                        'wenando_payment_intent_ref' => $paymentIntentId,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/v1/webhooks/payments/stripe',
            [],
            [],
            [],
            [
                'HTTP_Stripe-Signature' => $this->stripeSignatureHeader($payload),
                'CONTENT_TYPE' => 'application/json',
            ],
            $payload,
        )->assertOk()
            ->assertJsonPath('data.received', true);

        $this->assertDatabaseHas('wallets', [
            'company_id' => $this->company->id,
            'balance_credits' => 125,
        ]);

        $this->assertDatabaseHas('payment_intents', [
            'public_ref' => $paymentIntentId,
            'status' => PaymentIntentStatus::Completed->value,
            'provider' => 'stripe',
        ]);

        $this->assertSame(1, Transaction::query()
            ->where('company_id', $this->company->id)
            ->where('type', 'recharge')
            ->count());

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'stripe',
            'event_type' => 'payment_intent.succeeded',
            'status' => WebhookEventStatus::Processed->value,
        ]);
    }

    public function test_stripe_webhook_rejects_invalid_signature(): void
    {
        $payload = json_encode([
            'id' => 'evt_bad',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_x', 'object' => 'payment_intent', 'metadata' => []]],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/v1/webhooks/payments/stripe',
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => 't='.time().',v1=invalid'],
            $payload,
        )->assertStatus(401);
    }

    public function test_transfer_recharge_keeps_mock_client_secret_without_stripe(): void
    {
        Sanctum::actingAs($this->partner);

        $response = $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 15,
            'payment_method' => 'transfer',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.client_secret', null)
            ->assertJsonStructure(['data' => ['bank_transfer' => ['iban', 'reference']]]);

        $this->assertNull(FakeStripePaymentGateway::$lastCreate);

        $intent = PaymentIntent::query()
            ->where('company_id', $this->company->id)
            ->first();

        $this->assertNotNull($intent);
        $this->assertSame('bank_transfer', $intent->provider);
        $this->assertSame('WEN-'.$intent->id, $intent->provider_ref);
    }

    private function stripeSignatureHeader(string $payload): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, self::STRIPE_WEBHOOK_SECRET);

        return 't='.$timestamp.',v1='.$signature;
    }
}
