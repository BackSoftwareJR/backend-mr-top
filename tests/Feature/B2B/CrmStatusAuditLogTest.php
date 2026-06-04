<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\AuditAction;
use App\Enums\CrmStatus;
use App\Enums\LeadStatus;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadMatch;
use App\Models\Sector;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmStatusAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $partner;

    private LeadMatch $leadMatch;

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
            'dynamic_attributes' => ['service_areas' => ['Milano']],
        ]);

        Wallet::query()->create([
            'company_id' => $this->company->id,
            'balance_credits' => 150,
            'total_spent_credits' => 0,
            'currency' => 'EUR',
        ]);

        $this->partner = User::factory()->create([
            'user_type' => UserType::B2b,
            'email' => 'partner@struttura.it',
            'password' => Hash::make('password123'),
        ]);

        $this->company->users()->attach($this->partner->id, ['role' => 'owner']);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-1001',
            'sector_id' => $sector->id,
            'status' => LeadStatus::Routed,
            'payload' => [
                'autonomy' => 'parziale',
                'location' => ['label' => 'Milano (MI)', 'value' => 'milano-mi'],
            ],
            'contact_name' => 'Maria Rossi',
            'contact_phone' => '+39 333 123 4567',
            'contact_email' => 'maria@example.com',
            'location_label' => 'Milano (MI)',
            'budget_min' => 2000,
            'budget_max' => 2800,
            'need_summary' => 'Assistenza domiciliare h24',
        ]);

        $this->leadMatch = LeadMatch::query()->create([
            'lead_id' => $lead->id,
            'company_id' => $this->company->id,
            'match_score' => 98,
            'rank' => 1,
            'is_visible_to_consumer' => true,
            'is_in_marketplace' => true,
            'unlock_cost_credits' => 15,
        ]);
    }

    public function test_crm_status_patch_creates_audit_log_with_metadata(): void
    {
        Sanctum::actingAs($this->partner);

        $this->postJson('/api/v1/b2b/marketplace/leads/'.$this->leadMatch->public_ref.'/unlock')
            ->assertOk();

        $this->leadMatch->refresh();
        $this->assertSame(CrmStatus::Nuovo, $this->leadMatch->crm_status);

        $userAgent = 'WenandoTest/1.0 CRM-Audit';

        $response = $this->patchJson(
            '/api/v1/b2b/crm/clients/'.$this->leadMatch->public_ref.'/status',
            ['stato' => 'Contattato'],
            ['User-Agent' => $userAgent],
        );

        $response->assertOk()
            ->assertJsonPath('data.client.stato', 'Contattato');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->partner->id,
            'action' => AuditAction::CrmStatusUpdated->value,
            'subject_type' => LeadMatch::class,
            'subject_id' => $this->leadMatch->id,
        ]);

        $auditLog = AuditLog::query()
            ->forAction(AuditAction::CrmStatusUpdated)
            ->where('user_id', $this->partner->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($this->company->id, $auditLog->metadata['company_id']);
        $this->assertSame(CrmStatus::Nuovo->value, $auditLog->metadata['old_status']);
        $this->assertSame(CrmStatus::Contattato->value, $auditLog->metadata['new_status']);
        $this->assertSame($this->leadMatch->public_ref, $auditLog->metadata['public_ref']);
        $this->assertSame('PATCH', $auditLog->metadata['request_method']);
        $this->assertStringContainsString(
            'crm/clients/'.$this->leadMatch->public_ref.'/status',
            $auditLog->metadata['request_path'],
        );
        $this->assertSame($userAgent, $auditLog->metadata['user_agent']);
        $this->assertNotNull($auditLog->ip_address);
    }

    public function test_legacy_crm_client_patch_creates_audit_log(): void
    {
        Sanctum::actingAs($this->partner);

        $this->postJson('/api/v1/b2b/marketplace/leads/'.$this->leadMatch->public_ref.'/unlock')
            ->assertOk();

        $this->patchJson('/api/v1/b2b/crm/clients/'.$this->leadMatch->public_ref, [
            'stato' => 'Visita Fissata',
        ])->assertOk();

        $auditLog = AuditLog::query()
            ->forAction(AuditAction::CrmStatusUpdated)
            ->where('user_id', $this->partner->id)
            ->latestFirst()
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame(CrmStatus::Nuovo->value, $auditLog->metadata['old_status']);
        $this->assertSame(CrmStatus::VisitaFissata->value, $auditLog->metadata['new_status']);
    }
}
