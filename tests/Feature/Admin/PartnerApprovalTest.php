<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_pending_company(): void
    {
        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'user_type' => UserType::Superadmin,
        ]);

        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Residenza Aurora',
            'legal_name' => 'Residenza Aurora S.r.l.',
            'vetting_status' => VettingStatus::PendingReview,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/companies/'.$company->uuid.'/approve');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company.vetting_status', VettingStatus::Approved->value)
            ->assertJsonStructure(['data' => ['company' => ['id', 'approved_at']]]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'vetting_status' => VettingStatus::Approved->value,
        ]);

        $company->refresh();
        $this->assertNotNull($company->approved_at);
    }

    public function test_admin_can_reject_pending_company(): void
    {
        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'user_type' => UserType::Superadmin,
        ]);

        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Residenza Beta',
            'legal_name' => 'Residenza Beta S.r.l.',
            'vetting_status' => VettingStatus::PendingReview,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/companies/'.$company->uuid.'/reject', [
            'reason' => 'Documentazione incompleta',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.company.vetting_status', VettingStatus::Rejected->value);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'vetting_status' => VettingStatus::Rejected->value,
            'rejection_reason' => 'Documentazione incompleta',
        ]);
    }

    public function test_dashboard_stats_returns_aggregates(): void
    {
        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'user_type' => UserType::Superadmin,
        ]);

        Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Pending Co',
            'legal_name' => 'Pending Co S.r.l.',
            'vetting_status' => VettingStatus::PendingReview,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/admin/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.companies_pending_approval', 1)
            ->assertJsonStructure([
                'data' => [
                    'leads_today',
                    'wallet_recharge_revenue_cents',
                    'wallet_recharge_revenue_today_cents',
                    'companies_pending_approval',
                ],
            ]);
    }
}
