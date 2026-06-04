<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Company;
use App\Models\CompanyProfile;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BackfillCompanyProfilesCommandTest extends TestCase
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

    public function test_command_creates_profiles_for_companies_without_one(): void
    {
        $missingProfile = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $this->sector->id,
            'organization_name' => 'Casa Aurora',
            'legal_name' => 'Casa Aurora S.r.l.',
            'city' => 'Milano',
            'dynamic_attributes' => ['sector' => 'adi'],
        ]);

        $withProfile = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $this->sector->id,
            'organization_name' => 'Casa Già Profilata',
            'legal_name' => 'Casa Già Profilata S.r.l.',
            'city' => 'Roma',
        ]);

        CompanyProfile::query()->create([
            'company_id' => $withProfile->id,
            'display_name' => 'Casa Già Profilata',
            'service_type' => 'Assistenza Domiciliare',
        ]);

        $this->artisan('company-profiles:backfill')
            ->expectsOutputToContain('Created 1 company profile(s)')
            ->assertSuccessful();

        $this->assertDatabaseHas('company_profiles', [
            'company_id' => $missingProfile->id,
            'display_name' => 'Casa Aurora',
            'service_type' => 'Assistenza Domiciliare',
            'location_label' => 'Milano',
        ]);

        $this->assertDatabaseCount('company_profiles', 2);
    }

    public function test_dry_run_counts_without_creating_profiles(): void
    {
        Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $this->sector->id,
            'organization_name' => 'Dry Run Casa',
            'legal_name' => 'Dry Run Casa S.r.l.',
            'city' => 'Torino',
        ]);

        $this->artisan('company-profiles:backfill', ['--dry-run' => true])
            ->expectsOutputToContain('Would create 1 company profile(s)')
            ->assertSuccessful();

        $this->assertDatabaseCount('company_profiles', 0);
    }

    public function test_command_reports_zero_when_all_companies_have_profiles(): void
    {
        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $this->sector->id,
            'organization_name' => 'Completa',
            'legal_name' => 'Completa S.r.l.',
            'city' => 'Bologna',
        ]);

        CompanyProfile::query()->create([
            'company_id' => $company->id,
            'display_name' => 'Completa',
            'service_type' => 'Assistenza Senior Care',
        ]);

        $this->artisan('company-profiles:backfill', ['--dry-run' => true])
            ->expectsOutputToContain('Would create 0 company profile(s)')
            ->assertSuccessful();
    }
}
