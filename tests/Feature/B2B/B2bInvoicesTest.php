<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\Sector;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class B2bInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $partner;

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
            'organization_name' => 'Casa Serenità',
            'legal_name' => 'Casa Serenità S.r.l.',
            'city' => 'Milano',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
        ]);

        $wallet = Wallet::query()->create([
            'company_id' => $this->company->id,
            'balance_credits' => 100,
            'total_spent_credits' => 15,
            'currency' => 'EUR',
        ]);

        $this->partner = User::factory()->create([
            'user_type' => UserType::B2b,
            'email' => 'invoices@struttura.it',
            'password' => Hash::make('password123'),
        ]);

        $this->company->users()->attach($this->partner->id, ['role' => 'owner']);

        Transaction::query()->create([
            'uuid' => (string) Str::uuid(),
            'wallet_id' => $wallet->id,
            'company_id' => $this->company->id,
            'public_ref' => 'TX-RECHARGE-001',
            'type' => TransactionType::Recharge,
            'status' => TransactionStatus::Completed,
            'description' => 'Ricarica credito wallet',
            'amount_cents' => 10000,
            'credits_delta' => 100,
            'created_at' => now()->subDay(),
        ]);

        Transaction::query()->create([
            'uuid' => (string) Str::uuid(),
            'wallet_id' => $wallet->id,
            'company_id' => $this->company->id,
            'public_ref' => 'TX-UNLOCK-001',
            'type' => TransactionType::LeadUnlock,
            'status' => TransactionStatus::Completed,
            'description' => 'Sblocco lead ML-2039',
            'amount_cents' => 1500,
            'credits_delta' => -15,
            'created_at' => now(),
        ]);
    }

    public function test_b2b_invoices_list_returns_transactions_for_partner(): void
    {
        Sanctum::actingAs($this->partner);

        $response = $this->getJson('/api/v1/b2b/invoices');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.invoices')
            ->assertJsonFragment([
                'id' => 'TX-UNLOCK-001',
                'description' => 'Sblocco lead ML-2039',
                'amount' => 15,
                'status' => TransactionStatus::Completed->value,
            ])
            ->assertJsonFragment([
                'id' => 'TX-RECHARGE-001',
                'description' => 'Ricarica credito wallet',
                'amount' => 100,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'invoices' => [
                        ['id', 'date', 'description', 'amount', 'status'],
                    ],
                ],
                'meta' => ['request_id'],
            ]);
    }
}
