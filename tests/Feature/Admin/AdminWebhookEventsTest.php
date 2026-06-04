<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Enums\WebhookEventStatus;
use App\Models\Company;
use App\Models\PaymentIntent;
use App\Models\Sector;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminWebhookEventsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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
            'organization_name' => 'Webhook Co',
            'legal_name' => 'Webhook Co S.r.l.',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'dynamic_attributes' => [],
        ]);

        $this->admin = User::factory()->create([
            'user_type' => UserType::Superadmin,
            'email' => 'admin@wenando.com',
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_superadmin_lists_webhook_events_paginated(): void
    {
        $intent = PaymentIntent::query()->create([
            'public_ref' => 'PI-WEBHOOK-LOG-1',
            'company_id' => $this->company->id,
            'amount_cents' => 5000,
            'credits' => 50,
            'status' => PaymentIntentStatus::Completed,
            'payment_method' => PaymentMethod::Card,
            'provider' => 'stripe',
        ]);

        WebhookEvent::query()->create([
            'provider' => 'stripe',
            'event_type' => 'payment_intent.succeeded',
            'payload' => ['id' => 'evt_1', 'type' => 'payment_intent.succeeded'],
            'payment_intent_id' => $intent->id,
            'status' => WebhookEventStatus::Processed,
            'created_at' => now()->subMinute(),
        ]);

        WebhookEvent::query()->create([
            'provider' => 'mock',
            'event_type' => 'payment.failed',
            'payload' => ['payment_intent_id' => 'PI-MISSING', 'status' => 'failed'],
            'payment_intent_id' => null,
            'status' => WebhookEventStatus::Failed,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/admin/webhooks/events')
            ->assertOk()
            ->assertJsonPath('data.events.0.provider', 'mock')
            ->assertJsonPath('data.events.0.status', 'failed')
            ->assertJsonPath('data.events.1.provider', 'stripe')
            ->assertJsonPath('data.events.1.status', 'processed')
            ->assertJsonPath('data.events.1.payment_intent_id', 'PI-WEBHOOK-LOG-1')
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.total', 2);

        $this->assertCount(2, $response->json('data.events'));
    }

    public function test_superadmin_can_request_smaller_page_size(): void
    {
        for ($i = 0; $i < 3; $i++) {
            WebhookEvent::query()->create([
                'provider' => 'mock',
                'event_type' => 'payment.completed',
                'payload' => ['index' => $i],
                'status' => WebhookEventStatus::Processed,
                'created_at' => now()->subMinutes(3 - $i),
            ]);
        }

        $this->getJson('/api/v1/admin/webhooks/events?per_page=2&page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data.events');
    }

    public function test_per_page_is_capped_at_fifty(): void
    {
        $this->getJson('/api/v1/admin/webhooks/events?per_page=100')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 50);
    }

    public function test_non_superadmin_cannot_list_webhook_events(): void
    {
        $consumer = User::factory()->create(['user_type' => UserType::Consumer]);
        Sanctum::actingAs($consumer);

        $this->getJson('/api/v1/admin/webhooks/events')
            ->assertForbidden();
    }
}
