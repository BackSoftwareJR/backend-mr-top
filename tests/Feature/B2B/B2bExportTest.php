<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\AppointmentType;
use App\Enums\CrmStatus;
use App\Enums\LeadStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadMatch;
use App\Models\Sector;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class B2bExportTest extends TestCase
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
            'organization_name' => 'Export Co',
            'legal_name' => 'Export Co S.r.l.',
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
            'email' => 'export@partner.it',
            'password' => Hash::make('password123'),
        ]);

        $this->company->users()->attach($this->partner->id, ['role' => 'owner']);

        Sanctum::actingAs($this->partner);
    }

    public function test_partner_lists_available_export_types(): void
    {
        $this->getJson('/api/v1/b2b/exports')
            ->assertOk()
            ->assertJsonPath('data.exports.0.type', 'leads')
            ->assertJsonPath('data.exports.4.type', 'profile');
    }

    public function test_csv_export_has_headers_and_rows(): void
    {
        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-EXPORT-1',
            'sector_id' => $this->company->sector_id,
            'title' => 'Ricerca export',
            'contact_name' => 'Mario Rossi',
            'contact_email' => 'mario@example.com',
            'status' => LeadStatus::Matched,
            'payload' => [],
        ]);

        LeadMatch::query()->create([
            'lead_id' => $lead->id,
            'company_id' => $this->company->id,
            'match_score' => 90,
            'rank' => 1,
            'is_visible_to_consumer' => true,
            'is_in_marketplace' => true,
            'unlocked_at' => now(),
            'unlock_cost_credits' => 10,
            'crm_status' => CrmStatus::Nuovo,
        ]);

        Transaction::query()->create([
            'company_id' => $this->company->id,
            'wallet_id' => Wallet::query()->where('company_id', $this->company->id)->value('id'),
            'public_ref' => 'TX-EXPORT-1',
            'type' => TransactionType::Recharge,
            'amount_cents' => 5000,
            'credits_delta' => 50,
            'status' => TransactionStatus::Completed,
            'completed_at' => now(),
        ]);

        $response = $this->post('/api/v1/b2b/exports', [
            'type' => 'crm',
            'format' => 'csv',
        ])->assertOk();

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('public_ref', $content);
        $this->assertStringContainsString('contact_name', $content);
        $this->assertStringContainsString('Mario Rossi', $content);
    }
}
