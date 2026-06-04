<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\SectorSeeder;
use Illuminate\Console\Command;

/**
 * Idempotent reference data for production — no demo users or leads.
 */
class SeedSectorsCommand extends Command
{
    protected $signature = 'wenando:seed-sectors';

    protected $description = 'Seed required sector reference rows (senior-care, home-renovation stub)';

    public function handle(): int
    {
        $this->call(SectorSeeder::class);

        $this->components->info('Sectors seeded (senior-care active, home-renovation inactive).');

        return self::SUCCESS;
    }
}
