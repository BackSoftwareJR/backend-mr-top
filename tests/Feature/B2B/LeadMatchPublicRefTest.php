<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\CrmStatus;
use App\Enums\LeadStatus;
use App\Enums\UserType;
use App\Enums\VettingStatus;
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

class LeadMatchPublicRefTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    private Company $company;

    private User $partner;

    private LeadMatch $leadMatch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $this->sector->id,
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
            'sector_id' => $this->sector->id,
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

    public function test_lead_match_gets_public_ref_on_create(): void
    {
        $this->leadMatch->refresh();

        $this->assertNotNull($this->leadMatch->public_ref);
        $this->assertSame('ML-'.$this->leadMatch->id, $this->leadMatch->public_ref);

        $this->assertDatabaseHas('lead_matches', [
            'id' => $this->leadMatch->id,
            'public_ref' => 'ML-'.$this->leadMatch->id,
        ]);
    }

    public function test_crm_clients_list_exposes_public_ref_as_id(): void
    {
        Sanctum::actingAs($this->partner);

        $this->postJson('/api/v1/b2b/marketplace/leads/'.$this->leadMatch->public_ref.'/unlock')
            ->assertOk();

        $response = $this->getJson('/api/v1/b2b/crm/clients');

        $response->assertOk()
            ->assertJsonPath('data.clients.0.id', $this->leadMatch->public_ref)
            ->assertJsonPath('data.clients.0.marketplace_id', $this->leadMatch->public_ref);
    }

    public function test_crm_client_patch_by_public_ref(): void
    {
        Sanctum::actingAs($this->partner);

        $this->postJson('/api/v1/b2b/marketplace/leads/'.$this->leadMatch->public_ref.'/unlock')
            ->assertOk();

        $response = $this->patchJson('/api/v1/b2b/crm/clients/'.$this->leadMatch->public_ref, [
            'stato' => 'Contattato',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.client.id', $this->leadMatch->public_ref)
            ->assertJsonPath('data.client.stato', 'Contattato');

        $this->assertDatabaseHas('lead_matches', [
            'id' => $this->leadMatch->id,
            'public_ref' => $this->leadMatch->public_ref,
            'crm_status' => CrmStatus::Contattato->value,
        ]);
    }

    public function test_crm_client_status_patch_by_public_ref(): void
    {
        Sanctum::actingAs($this->partner);

        $this->postJson('/api/v1/b2b/marketplace/leads/'.$this->leadMatch->public_ref.'/unlock')
            ->assertOk();

        $response = $this->patchJson('/api/v1/b2b/crm/clients/'.$this->leadMatch->public_ref.'/status', [
            'stato' => 'Visita Fissata',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.client.id', $this->leadMatch->public_ref)
            ->assertJsonPath('data.client.stato', 'Visita Fissata');

        $this->assertDatabaseHas('lead_matches', [
            'id' => $this->leadMatch->id,
            'crm_status' => CrmStatus::VisitaFissata->value,
        ]);
    }

    public function test_lead_match_resolve_route_binding_accepts_public_ref(): void
    {
        $resolved = (new LeadMatch)->resolveRouteBinding($this->leadMatch->public_ref);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($this->leadMatch));
    }
}
