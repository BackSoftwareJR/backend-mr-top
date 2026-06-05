<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AppLogChannel;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Enums\WebhookEventStatus;
use App\Models\AppLog;
use App\Models\Company;
use App\Models\PaymentIntent;
use App\Models\Sector;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSystemLogsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'user_type' => UserType::Superadmin,
            'email' => 'admin@wenando.com',
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_superadmin_lists_app_logs_paginated(): void
    {
        AppLog::query()->create([
            'request_id' => '01JADMINLOGTEST00000001',
            'channel' => AppLogChannel::Api,
            'level' => 'error',
            'message' => 'api.exception',
            'context' => ['route' => 'api/v1/test'],
            'created_at' => now(),
        ]);

        AppLog::query()->create([
            'request_id' => '01JADMINLOGTEST00000002',
            'channel' => AppLogChannel::Webhook,
            'level' => 'error',
            'message' => 'webhook.processing_failed',
            'created_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/v1/admin/system/logs')
            ->assertOk()
            ->assertJsonPath('data.logs.0.channel', 'api')
            ->assertJsonPath('data.logs.1.channel', 'webhook')
            ->assertJsonPath('meta.retention_days', 7)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_superadmin_filters_logs_by_request_id(): void
    {
        AppLog::query()->create([
            'request_id' => '01JFILTERBYREQUESTID001',
            'channel' => AppLogChannel::Api,
            'level' => 'error',
            'message' => 'api.exception',
            'created_at' => now(),
        ]);

        AppLog::query()->create([
            'request_id' => '01JOTHERREQUESTID00002',
            'channel' => AppLogChannel::Api,
            'level' => 'error',
            'message' => 'api.exception',
            'created_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/system/logs?request_id=01JFILTERBYREQUESTID001')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.logs.0.request_id', '01JFILTERBYREQUESTID001');
    }

    public function test_non_superadmin_cannot_list_system_logs(): void
    {
        $consumer = User::factory()->create(['user_type' => UserType::Consumer]);
        Sanctum::actingAs($consumer);

        $this->getJson('/api/v1/admin/system/logs')
            ->assertForbidden();
    }
}
