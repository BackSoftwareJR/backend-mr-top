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

class AdminWalletPendingTransfersTest extends TestCase
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
            'organization_name' => 'Bonifico Co',
            'legal_name' => 'Bonifico Co S.r.l.',
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

    public function test_superadmin_lists_pending_bank_transfers(): void
    {
        $pending = PaymentIntent::query()->create([
            'public_ref' => 'PI-PENDING-TRANSFER-1',
            'company_id' => $this->company->id,
            'amount_cents' => 5000,
            'credits' => 50,
            'status' => PaymentIntentStatus::Pending,
            'payment_method' => PaymentMethod::Transfer,
            'provider' => 'bank_transfer',
            'provider_ref' => 'WEN-101',
        ]);

        PaymentIntent::query()->create([
            'public_ref' => 'PI-COMPLETED-TRANSFER-1',
            'company_id' => $this->company->id,
            'amount_cents' => 3000,
            'credits' => 30,
            'status' => PaymentIntentStatus::Completed,
            'payment_method' => PaymentMethod::Transfer,
            'provider' => 'bank_transfer',
            'provider_ref' => 'WEN-102',
        ]);

        PaymentIntent::query()->create([
            'public_ref' => 'PI-PENDING-CARD-1',
            'company_id' => $this->company->id,
            'amount_cents' => 2000,
            'credits' => 20,
            'status' => PaymentIntentStatus::Pending,
            'payment_method' => PaymentMethod::Card,
            'provider' => 'stripe',
        ]);

        $response = $this->getJson('/api/v1/admin/wallet/pending-transfers')
            ->assertOk()
            ->assertJsonPath('data.pending_transfers.0.id', $pending->public_ref)
            ->assertJsonPath('data.pending_transfers.0.company_name', 'Bonifico Co')
            ->assertJsonPath('data.pending_transfers.0.credits', 50)
            ->assertJsonPath('data.pending_transfers.0.reference', 'WEN-101');

        $this->assertCount(1, $response->json('data.pending_transfers'));
        $this->assertNotEmpty($response->json('data.pending_transfers.0.created_at'));
    }

    public function test_non_superadmin_cannot_list_pending_transfers(): void
    {
        $consumer = User::factory()->create(['user_type' => UserType::Consumer]);
        Sanctum::actingAs($consumer);

        $this->getJson('/api/v1/admin/wallet/pending-transfers')
            ->assertForbidden();
    }
}
