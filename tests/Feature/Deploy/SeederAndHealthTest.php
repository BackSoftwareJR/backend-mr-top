<?php

declare(strict_types=1);

namespace Tests\Feature\Deploy;

use App\Models\Sector;
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
