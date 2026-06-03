<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

class SectorSeeder extends Seeder
{
    public function run(): void
    {
        Sector::query()->updateOrCreate(
            ['slug' => 'senior-care'],
            [
                'name' => 'Senior Care',
                'is_active' => true,
                'wizard_schema' => [
                    'id' => 'wenando-intake-v3',
                    'title' => 'Analisi gratuita',
                    'steps' => [
                        ['id' => 'autonomy', 'type' => 'cards'],
                        ['id' => 'location', 'type' => 'autocomplete'],
                        ['id' => 'budget', 'type' => 'range-slider'],
                        ['id' => 'contact', 'type' => 'contact-form'],
                    ],
                ],
                'operations_schema' => [
                    'fields' => ['sector', 'capacity', 'nonSelfSufficient', 'nightStaff', 'notes'],
                ],
                'trust_schema' => [
                    'questions' => ['emergency', 'fall', 'family', 'quality'],
                ],
                'matching_rules' => [
                    'default_unlock_cost' => 15,
                    'min_match_score_marketplace' => 80,
                ],
            ],
        );

        Sector::query()->updateOrCreate(
            ['slug' => 'home-renovation'],
            [
                'name' => 'Home Renovation',
                'is_active' => false,
                'wizard_schema' => [
                    'id' => 'wenando-renovation-v1',
                    'title' => 'Preventivo ristrutturazione',
                    'steps' => [],
                ],
                'operations_schema' => ['fields' => []],
                'trust_schema' => ['questions' => []],
                'matching_rules' => [],
            ],
        );
    }
}
