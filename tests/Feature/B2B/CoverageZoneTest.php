<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\CompanyCoverageZone;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoverageZoneTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $partner;

    private const PRIVACY_HASH = 'ba8597379922b6fe4154e6448474495391e40e7fa2cd8688f51cf159f7252006';

    private const TERMS_B2B_HASH = '3660d5b1e1b49d528e87bf7d844abd9eee72dec18dd798bee1d097bd3d1ce008';

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
            'organization_name' => 'Casa Copertura',
            'legal_name' => 'Casa Copertura S.r.l.',
            'city' => 'Roma',
            'vetting_status' => VettingStatus::InProgress,
        ]);

        $this->partner = User::factory()->create([
            'user_type' => UserType::B2b,
            'email' => 'coverage@struttura.it',
            'password' => Hash::make('password123'),
        ]);

        $this->company->users()->attach($this->partner->id, ['role' => 'owner']);
    }

    public function test_get_returns_null_when_no_zone(): void
    {
        Sanctum::actingAs($this->partner);

        $this->getJson('/api/v1/b2b/coverage-zone')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.coverage_zone', null);
    }

    public function test_put_creates_coverage_zone(): void
    {
        Sanctum::actingAs($this->partner);

        $payload = [
            'center_lat' => 41.9028,
            'center_lng' => 12.4964,
            'radius_km' => 25,
            'label' => 'Roma centro',
            'geocode_place_id' => 'rome-centre',
            'geocode_meta' => ['city' => 'Roma'],
        ];

        $this->putJson('/api/v1/b2b/coverage-zone', $payload)
            ->assertOk()
            ->assertJsonPath('data.coverage_zone.center_lat', 41.9028)
            ->assertJsonPath('data.coverage_zone.center_lng', 12.4964)
            ->assertJsonPath('data.coverage_zone.radius_km', 25)
            ->assertJsonPath('data.coverage_zone.label', 'Roma centro');

        $this->assertDatabaseHas('company_coverage_zones', [
            'company_id' => $this->company->id,
            'center_lat' => 41.9028000,
            'center_lng' => 12.4964000,
            'radius_km' => 25.00,
            'label' => 'Roma centro',
        ]);
    }

    public function test_put_updates_existing_zone_idempotently(): void
    {
        CompanyCoverageZone::query()->create([
            'company_id' => $this->company->id,
            'center_lat' => 45.4642,
            'center_lng' => 9.1900,
            'radius_km' => 10,
            'label' => 'Milano',
        ]);

        Sanctum::actingAs($this->partner);

        $this->putJson('/api/v1/b2b/coverage-zone', [
            'center_lat' => 41.9028,
            'center_lng' => 12.4964,
            'radius_km' => 15.5,
            'label' => 'Roma aggiornata',
        ])
            ->assertOk()
            ->assertJsonPath('data.coverage_zone.label', 'Roma aggiornata')
            ->assertJsonPath('data.coverage_zone.radius_km', 15.5);

        $this->assertDatabaseCount('company_coverage_zones', 1);
        $this->assertDatabaseHas('company_coverage_zones', [
            'company_id' => $this->company->id,
            'label' => 'Roma aggiornata',
            'radius_km' => 15.50,
        ]);
    }

    public function test_put_rejects_radius_above_80(): void
    {
        Sanctum::actingAs($this->partner);

        $this->putJson('/api/v1/b2b/coverage-zone', [
            'center_lat' => 41.9028,
            'center_lng' => 12.4964,
            'radius_km' => 120,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertDatabaseCount('company_coverage_zones', 0);
    }

    public function test_put_rejects_invalid_coordinates(): void
    {
        Sanctum::actingAs($this->partner);

        $this->putJson('/api/v1/b2b/coverage-zone', [
            'center_lat' => 95,
            'center_lng' => 12.4964,
            'radius_km' => 10,
        ])->assertUnprocessable();

        $this->putJson('/api/v1/b2b/coverage-zone', [
            'center_lat' => 41.9028,
            'center_lng' => 200,
            'radius_km' => 10,
        ])->assertUnprocessable();
    }

    public function test_delete_removes_coverage_zone(): void
    {
        CompanyCoverageZone::query()->create([
            'company_id' => $this->company->id,
            'center_lat' => 41.9028,
            'center_lng' => 12.4964,
            'radius_km' => 20,
        ]);

        Sanctum::actingAs($this->partner);

        $this->deleteJson('/api/v1/b2b/coverage-zone')
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('company_coverage_zones', [
            'company_id' => $this->company->id,
        ]);
    }

    public function test_patch_onboarding_accepts_coverage_zone(): void
    {
        Sanctum::actingAs($this->partner);

        $this->patchJson('/api/v1/b2b/onboarding', [
            'coverage_zone' => [
                'center_lat' => 44.4949,
                'center_lng' => 11.3426,
                'radius_km' => 30,
                'label' => 'Bologna',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.data.coverage_zone.label', 'Bologna');

        $this->assertDatabaseHas('company_coverage_zones', [
            'company_id' => $this->company->id,
            'label' => 'Bologna',
        ]);
    }

    public function test_onboarding_submit_blocked_without_coverage_zone(): void
    {
        Sanctum::actingAs($this->partner);

        $this->postJson('/api/v1/b2b/onboarding/submit', [
            'terms_b2b_accepted' => true,
            'terms_text_hash' => self::TERMS_B2B_HASH,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.coverage_zone.0', 'Devi configurare la zona di copertura prima di inviare il profilo.');
    }

    public function test_onboarding_submit_succeeds_with_coverage_zone(): void
    {
        Sanctum::actingAs($this->partner);

        CompanyCoverageZone::query()->create([
            'company_id' => $this->company->id,
            'center_lat' => 41.9028,
            'center_lng' => 12.4964,
            'radius_km' => 20,
        ]);

        $this->postJson('/api/v1/b2b/onboarding/submit', [
            'terms_b2b_accepted' => true,
            'terms_text_hash' => self::TERMS_B2B_HASH,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', VettingStatus::PendingReview->value);
    }

    public function test_consumer_cannot_access_coverage_zone(): void
    {
        $consumer = User::factory()->create(['user_type' => UserType::Consumer]);
        Sanctum::actingAs($consumer);

        $this->getJson('/api/v1/b2b/coverage-zone')->assertForbidden();
        $this->putJson('/api/v1/b2b/coverage-zone', [
            'center_lat' => 41.9028,
            'center_lng' => 12.4964,
            'radius_km' => 10,
        ])->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/b2b/coverage-zone')->assertUnauthorized();
        $this->putJson('/api/v1/b2b/coverage-zone', [
            'center_lat' => 41.9028,
            'center_lng' => 12.4964,
            'radius_km' => 10,
        ])->assertUnauthorized();
    }

    public function test_register_flow_can_save_zone_before_submit(): void
    {
        $this->postJson('/api/v1/b2b/register', [
            'email' => 'zoneflow@struttura.it',
            'organization_name' => 'Flow Zona',
            'legal_name' => 'Flow Zona S.r.l.',
            'privacy_policy_accepted' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
        ])->assertCreated();

        $user = User::query()->where('email', 'zoneflow@struttura.it')->firstOrFail();
        Sanctum::actingAs($user, ['*']);

        $this->putJson('/api/v1/b2b/coverage-zone', [
            'center_lat' => 45.0703,
            'center_lng' => 7.6869,
            'radius_km' => 12,
            'label' => 'Torino',
        ])->assertOk();

        $this->postJson('/api/v1/b2b/onboarding/submit', [
            'terms_b2b_accepted' => true,
            'terms_text_hash' => self::TERMS_B2B_HASH,
        ])->assertOk();
    }
}
