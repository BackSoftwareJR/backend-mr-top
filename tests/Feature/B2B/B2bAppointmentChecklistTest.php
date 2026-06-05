<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\AppointmentType;
use App\Enums\CrmStatus;
use App\Enums\LeadStatus;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Appointment;
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

class B2bAppointmentChecklistTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $partner;

    private Appointment $appointment;

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
            'organization_name' => 'Checklist Co',
            'legal_name' => 'Checklist Co S.r.l.',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'dynamic_attributes' => [],
        ]);

        Wallet::query()->create([
            'company_id' => $this->company->id,
            'balance_credits' => 50,
            'total_spent_credits' => 0,
            'currency' => 'EUR',
        ]);

        $this->partner = User::factory()->create([
            'user_type' => UserType::B2b,
            'email' => 'checklist@partner.it',
            'password' => Hash::make('password123'),
        ]);

        $this->company->users()->attach($this->partner->id, ['role' => 'owner']);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-CHECKLIST-1',
            'sector_id' => $sector->id,
            'title' => 'Visita checklist',
            'contact_name' => 'Luigi Verdi',
            'status' => LeadStatus::Matched,
            'payload' => [],
        ]);

        $match = LeadMatch::query()->create([
            'lead_id' => $lead->id,
            'company_id' => $this->company->id,
            'match_score' => 88,
            'rank' => 1,
            'is_visible_to_consumer' => true,
            'is_in_marketplace' => true,
            'unlocked_at' => now(),
            'unlock_cost_credits' => 10,
            'crm_status' => CrmStatus::VisitaFissata,
        ]);

        $this->appointment = Appointment::query()->create([
            'company_id' => $this->company->id,
            'lead_match_id' => $match->id,
            'client_name' => 'Luigi Verdi',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'type' => AppointmentType::Visit,
        ]);

        Sanctum::actingAs($this->partner);
    }

    public function test_partner_updates_appointment_checklist(): void
    {
        $checklist = [
            ['id' => 'task-1', 'label' => 'Preparare documenti', 'done' => false],
            ['id' => 'task-2', 'label' => 'Confermare orario', 'done' => true],
        ];

        $this->patchJson('/api/v1/b2b/appointments/'.$this->appointment->id, [
            'checklist' => $checklist,
        ])
            ->assertOk()
            ->assertJsonPath('data.appointment.checklist.0.label', 'Preparare documenti')
            ->assertJsonPath('data.appointment.checklist.1.done', true);

        $this->assertDatabaseHas('appointments', [
            'id' => $this->appointment->id,
        ]);

        $this->appointment->refresh();
        $this->assertSame($checklist, $this->appointment->checklist);
    }
}
