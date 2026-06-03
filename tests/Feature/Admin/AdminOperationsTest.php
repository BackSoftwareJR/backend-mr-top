<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'user_type' => UserType::Superadmin,
            'email' => 'admin@wenando.com',
        ]);

        Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Pending Co',
            'legal_name' => 'Pending Co S.r.l.',
            'vetting_status' => VettingStatus::PendingReview,
        ]);

        Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'status' => 'processing',
            'payload' => ['autonomy' => 'parziale'],
            'contact_name' => 'Test User',
            'location_label' => 'Milano',
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_admin_lists_partners_and_leads(): void
    {
        $this->getJson('/api/v1/admin/partners')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['partners']]);

        $this->getJson('/api/v1/admin/leads')
            ->assertOk()
            ->assertJsonStructure(['data' => ['leads']]);
    }

    public function test_admin_suspend_partner(): void
    {
        $company = Company::query()->first();
        $company->update(['vetting_status' => VettingStatus::Approved]);

        $this->postJson("/api/v1/admin/partners/{$company->uuid}/suspend", [
            'reason' => 'Test suspend',
        ])->assertOk()
            ->assertJsonPath('data.company.vetting_status', VettingStatus::Suspended->value);
    }
}
