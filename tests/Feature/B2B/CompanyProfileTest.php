<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\CompanyProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $partner;

    private Company $otherCompany;

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
            'dynamic_attributes' => ['sector' => 'adi'],
        ]);

        $this->otherCompany = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Altra Casa',
            'legal_name' => 'Altra Casa S.r.l.',
            'city' => 'Roma',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
        ]);

        $this->partner = User::factory()->create([
            'user_type' => UserType::B2b,
            'email' => 'partner@struttura.it',
            'password' => Hash::make('password123'),
        ]);

        $this->company->users()->attach($this->partner->id, ['role' => 'owner']);

        CompanyProfile::query()->create([
            'company_id' => $this->company->id,
            'display_name' => 'Casa Serenità',
            'service_type' => 'Assistenza Domiciliare',
            'tagline' => 'Tagline iniziale',
            'description' => 'Descrizione iniziale',
            'pros' => ['Punto uno'],
            'image_url' => 'https://example.com/logo.jpg',
            'location_label' => 'Milano, Zona Navigli',
            'contact_hint' => 'Contattaci entro 24 ore',
        ]);
    }

    public function test_partner_can_view_own_company_profile(): void
    {
        Sanctum::actingAs($this->partner);

        $this->getJson('/api/v1/b2b/company/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company.organization_name', 'Casa Serenità')
            ->assertJsonPath('data.profile.display_name', 'Casa Serenità')
            ->assertJsonPath('data.profile.tagline', 'Tagline iniziale')
            ->assertJsonPath('data.profile.pros.0', 'Punto uno');
    }

    public function test_partner_can_patch_own_company_profile(): void
    {
        Sanctum::actingAs($this->partner);

        $this->patchJson('/api/v1/b2b/company/profile', [
            'display_name' => 'Casa Serenità Aggiornata',
            'tagline' => 'Nuovo tagline',
            'description' => 'Nuova descrizione',
            'pros' => ['Operatori certificati', 'Orari flessibili'],
            'image_url' => 'https://example.com/new.jpg',
            'location_label' => 'Milano, Porta Romana',
            'contact_hint' => 'WhatsApp H24',
        ])
            ->assertOk()
            ->assertJsonPath('data.profile.display_name', 'Casa Serenità Aggiornata')
            ->assertJsonPath('data.profile.tagline', 'Nuovo tagline')
            ->assertJsonPath('data.profile.pros.1', 'Orari flessibili');

        $this->assertDatabaseHas('company_profiles', [
            'company_id' => $this->company->id,
            'display_name' => 'Casa Serenità Aggiornata',
            'contact_hint' => 'WhatsApp H24',
        ]);
    }

    public function test_patch_creates_profile_when_missing(): void
    {
        CompanyProfile::query()->where('company_id', $this->company->id)->delete();

        Sanctum::actingAs($this->partner);

        $this->patchJson('/api/v1/b2b/company/profile', [
            'tagline' => 'Profilo appena creato',
        ])
            ->assertOk()
            ->assertJsonPath('data.profile.display_name', 'Casa Serenità')
            ->assertJsonPath('data.profile.service_type', 'Assistenza Domiciliare')
            ->assertJsonPath('data.profile.tagline', 'Profilo appena creato');

        $this->assertDatabaseHas('company_profiles', [
            'company_id' => $this->company->id,
            'display_name' => 'Casa Serenità',
            'tagline' => 'Profilo appena creato',
        ]);
    }

    public function test_get_returns_auto_seeded_profile_after_register(): void
    {
        Sector::query()->firstOrCreate(
            ['slug' => 'senior-care'],
            ['name' => 'Senior Care', 'is_active' => true],
        );

        $this->postJson('/api/v1/b2b/register', [
            'email' => 'profileseed@struttura.it',
            'organization_name' => 'Seed Profilo',
            'legal_name' => 'Seed Profilo S.r.l.',
            'privacy_policy_accepted' => true,
            'consent_text_hash' => 'ba8597379922b6fe4154e6448474495391e40e7fa2cd8688f51cf159f7252006',
        ])->assertCreated();

        $partner = User::query()->where('email', 'profileseed@struttura.it')->firstOrFail();
        Sanctum::actingAs($partner);

        $this->getJson('/api/v1/b2b/company/profile')
            ->assertOk()
            ->assertJsonPath('data.profile.display_name', 'Seed Profilo')
            ->assertJsonPath('data.profile.service_type', 'Assistenza Senior Care');
    }

    public function test_consumer_cannot_access_company_profile(): void
    {
        $consumer = User::factory()->create(['user_type' => UserType::Consumer]);
        Sanctum::actingAs($consumer);

        $this->getJson('/api/v1/b2b/company/profile')->assertForbidden();
        $this->patchJson('/api/v1/b2b/company/profile', [
            'tagline' => 'Hack',
        ])->assertForbidden();
    }

    public function test_patch_rejects_other_company_id(): void
    {
        Sanctum::actingAs($this->partner);

        $this->patchJson('/api/v1/b2b/company/profile', [
            'company_id' => $this->otherCompany->id,
            'tagline' => 'Tentativo cross-tenant',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_partner_only_sees_own_company_data(): void
    {
        $otherPartner = User::factory()->create(['user_type' => UserType::B2b]);
        $this->otherCompany->users()->attach($otherPartner->id, ['role' => 'owner']);

        CompanyProfile::query()->create([
            'company_id' => $this->otherCompany->id,
            'display_name' => 'Altra Casa',
            'service_type' => 'RSA',
            'tagline' => 'Segreto',
        ]);

        Sanctum::actingAs($this->partner);

        $this->getJson('/api/v1/b2b/company/profile')
            ->assertOk()
            ->assertJsonPath('data.company.organization_name', 'Casa Serenità')
            ->assertJsonPath('data.profile.tagline', 'Tagline iniziale')
            ->assertJsonMissing(['data.profile.tagline' => 'Segreto']);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/b2b/company/profile')->assertUnauthorized();
        $this->patchJson('/api/v1/b2b/company/profile', ['tagline' => 'x'])->assertUnauthorized();
    }
}
