<?php

declare(strict_types=1);

namespace Tests\Feature\Deploy;

use App\Enums\LeadStatus;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederAndHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_senior_care_sector(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('sectors', [
            'slug' => 'senior-care',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('sectors', [
            'slug' => 'home-renovation',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('roles', ['name' => 'consumer']);
        $this->assertDatabaseHas('roles', ['name' => 'super_admin']);
    }

    public function test_database_seeder_creates_dev_users_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $superadminEmail = env('SEED_SUPERADMIN_EMAIL', 'admin@wenando.test');

        $this->assertDatabaseHas('users', [
            'email' => $superadminEmail,
            'user_type' => UserType::Superadmin->value,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'consumer@wenando.test',
            'user_type' => UserType::Consumer->value,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'partner@care.it',
            'user_type' => UserType::B2b->value,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'partner-pending@wenando.test',
            'user_type' => UserType::B2b->value,
        ]);

        $this->assertDatabaseHas('leads', [
            'public_ref' => 'LD-SEED-CONSUMER',
            'status' => LeadStatus::Routed->value,
        ]);

        $this->assertDatabaseHas('companies', [
            'organization_name' => 'Care Partner Italia',
            'vetting_status' => VettingStatus::Approved->value,
        ]);

        $this->assertDatabaseHas('companies', [
            'organization_name' => 'Residenza In Attesa',
            'vetting_status' => VettingStatus::PendingReview->value,
        ]);

        $approvedPartner = User::query()->where('email', 'partner@care.it')->firstOrFail();
        $companyId = $approvedPartner->companies()->firstOrFail()->id;

        $this->assertDatabaseHas('company_profiles', [
            'company_id' => $companyId,
            'display_name' => 'Care Partner Italia',
        ]);

        $this->assertDatabaseHas('wallets', [
            'company_id' => $companyId,
            'balance_credits' => 150,
        ]);
    }

    public function test_health_endpoint_returns_ok_envelope(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.db', true)
            ->assertJsonPath('data.queue', true);
    }

    public function test_b2c_lead_requires_seeded_sector(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(
            Sector::query()->where('slug', 'senior-care')->where('is_active', true)->exists(),
        );
    }
}
