<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SectorSeeder::class,
            RolesPermissionsSeeder::class,
            EditorialRubricSeeder::class,
            EditorialPermissionSeeder::class,
            AdvisorProfileSeeder::class,
            DemoMatchCompaniesSeeder::class,
            DevUsersSeeder::class,
        ]);
    }
}
