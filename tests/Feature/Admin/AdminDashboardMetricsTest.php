<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\PaymentIntent;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
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
            'organization_name' => 'Metrics Co',
            'legal_name' => 'Metrics Co S.r.l.',
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

    public function test_dashboard_stats_includes_pending_bank_transfers_count(): void
    {
        PaymentIntent::query()->create([
            'public_ref' => 'PI-METRICS-PENDING-1',
            'company_id' => $this->company->id,
            'amount_cents' => 2500,
            'credits' => 25,
            'status' => PaymentIntentStatus::Pending,
            'payment_method' => PaymentMethod::Transfer,
            'provider' => 'bank_transfer',
            'provider_ref' => 'WEN-201',
        ]);

        PaymentIntent::query()->create([
            'public_ref' => 'PI-METRICS-PENDING-2',
            'company_id' => $this->company->id,
            'amount_cents' => 1500,
            'credits' => 15,
            'status' => PaymentIntentStatus::Pending,
            'payment_method' => PaymentMethod::Transfer,
            'provider' => 'bank_transfer',
            'provider_ref' => 'WEN-202',
        ]);

        PaymentIntent::query()->create([
            'public_ref' => 'PI-METRICS-COMPLETED-1',
            'company_id' => $this->company->id,
            'amount_cents' => 1000,
            'credits' => 10,
            'status' => PaymentIntentStatus::Completed,
            'payment_method' => PaymentMethod::Transfer,
            'provider' => 'bank_transfer',
            'provider_ref' => 'WEN-203',
        ]);

        PaymentIntent::query()->create([
            'public_ref' => 'PI-METRICS-CARD-1',
            'company_id' => $this->company->id,
            'amount_cents' => 2000,
            'credits' => 20,
            'status' => PaymentIntentStatus::Pending,
            'payment_method' => PaymentMethod::Card,
            'provider' => 'stripe',
        ]);

        $this->getJson('/api/v1/admin/metrics')
            ->assertOk()
            ->assertJsonPath('data.pending_bank_transfers_count', 2);

        $this->getJson('/api/v1/admin/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.pending_bank_transfers_count', 2);
    }
}
