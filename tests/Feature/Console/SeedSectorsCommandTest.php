<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedSectorsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_wenando_seed_sectors_creates_senior_care(): void
    {
        $this->assertDatabaseCount('sectors', 0);

        $this->artisan('wenando:seed-sectors')
            ->assertSuccessful();

        $this->assertDatabaseHas('sectors', [
            'slug' => 'senior-care',
            'is_active' => true,
        ]);

        $this->artisan('wenando:seed-sectors')
            ->assertSuccessful();

        $this->assertSame(2, Sector::query()->count());
    }
}
