<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Enums\AppointmentType;
use App\Enums\AuditAction;
use App\Enums\ConsentType;
use App\Enums\LeadStatus;
use App\Enums\UserType;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\ConsentLog;
use App\Models\Lead;
use App\Models\SavedMatch;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivacyExportTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);
    }

    public function test_authenticated_consumer_receives_dsar_json_export(): void
    {
        $user = User::factory()->create([
            'user_type' => UserType::Consumer,
            'email' => 'dsar@example.com',
            'name' => 'Mario Export',
            'phone' => '+39 333 111 2222',
        ]);

        ConsentLog::query()->create([
            'user_id' => $user->id,
            'consent_type' => ConsentType::PrivacyPolicy,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => str_repeat('a', 64),
        ]);

        $ownedLead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-9001',
            'sector_id' => $this->sector->id,
            'user_id' => $user->id,
            'status' => LeadStatus::Routed,
            'contact_name' => 'Mario Export',
            'contact_email' => 'dsar@example.com',
            'contact_phone' => '+39 333 111 2222',
            'location_label' => 'Roma (RM)',
            'need_summary' => 'Assistenza domiciliare',
            'payload' => ['autonomy' => 'parziale'],
        ]);

        Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-9002',
            'sector_id' => $this->sector->id,
            'status' => LeadStatus::Closed,
            'contact_email' => 'dsar@example.com',
            'contact_name' => 'Mario Export',
            'location_label' => 'Torino (TO)',
            'payload' => ['autonomy' => 'autosufficiente'],
        ]);

        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $this->sector->id,
            'organization_name' => 'Struttura Test',
            'legal_name' => 'Struttura Test S.r.l.',
            'city' => 'Milano',
        ]);

        SavedMatch::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        Appointment::query()->create([
            'user_id' => $user->id,
            'type' => AppointmentType::Advisor,
            'client_name' => 'Mario Export',
            'scheduled_date' => '2026-07-15',
            'scheduled_time' => '10:00',
            'note' => 'Prima consulenza',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/user/privacy/export');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.format', 'json')
            ->assertJsonPath('data.format_version', '1.0')
            ->assertJsonPath('data.profile.email', 'dsar@example.com')
            ->assertJsonPath('data.profile.name', 'Mario Export')
            ->assertJsonPath('data.consents.0.consent_type', ConsentType::PrivacyPolicy->value)
            ->assertJsonCount(2, 'data.leads')
            ->assertJsonPath('data.leads.0.public_ref', 'LD-9001')
            ->assertJsonPath('data.saved_matches.0.company_name', 'Struttura Test')
            ->assertJsonPath('data.advisor_bookings.0.scheduled_date', '2026-07-15')
            ->assertJsonStructure([
                'data' => [
                    'exported_at',
                    'profile' => ['uuid', 'email', 'name', 'phone', 'created_at'],
                    'consents',
                    'leads',
                    'saved_matches',
                    'advisor_bookings',
                ],
            ]);

        $leadPublicRefs = collect($response->json('data.leads'))->pluck('public_ref')->all();
        $this->assertContains('LD-9001', $leadPublicRefs);
        $this->assertContains('LD-9002', $leadPublicRefs);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::PrivacyExport->value,
        ]);
    }

    public function test_privacy_export_requires_authentication(): void
    {
        $this->getJson('/api/v1/user/privacy/export')->assertUnauthorized();
    }

    public function test_privacy_export_requires_consumer_role(): void
    {
        $partner = User::factory()->create(['user_type' => UserType::B2b]);
        Sanctum::actingAs($partner);

        $this->getJson('/api/v1/user/privacy/export')->assertForbidden();
    }
}
