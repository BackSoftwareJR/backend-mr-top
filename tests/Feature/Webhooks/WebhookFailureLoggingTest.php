<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Enums\AppLogChannel;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\PaymentIntent;
use App\Models\Sector;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookFailureLoggingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Webhook Fail Co',
            'legal_name' => 'Webhook Fail Co S.r.l.',
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
    }

    public function test_webhook_failure_creates_app_log_row(): void
    {
        config(['wenando.webhook_secret' => 'test-webhook-secret']);

        PaymentIntent::query()->create([
            'public_ref' => 'PI-WEBHOOK-FAIL-1',
            'company_id' => $this->company->id,
            'amount_cents' => 5000,
            'credits' => 50,
            'status' => PaymentIntentStatus::Pending,
            'payment_method' => PaymentMethod::Card,
            'provider' => 'mock',
        ]);

        $response = $this->postJson('/api/v1/webhooks/payments/mock', [
            'payment_intent_id' => 'PI-NONEXISTENT',
            'status' => 'completed',
        ], [
            'X-Wenando-Webhook-Secret' => 'test-webhook-secret',
        ])->assertNotFound();

        $requestId = $response->json('meta.request_id');
        $this->assertIsString($requestId);

        $this->assertDatabaseHas('app_logs', [
            'request_id' => $requestId,
            'channel' => AppLogChannel::Webhook->value,
            'level' => 'error',
            'message' => 'webhook.processing_failed',
        ]);
    }
}
