<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EditorialRubric;
use Illuminate\Database\Seeder;

class EditorialRubricSeeder extends Seeder
{
    /**
     * @var list<array{slug: string, name: string, description: string, sort_order: int}>
     */
    private const RUBRICS = [
        [
            'slug' => 'anti-truffe',
            'name' => 'Anti-truffe',
            'description' => 'Guide per riconoscere truffe e pratiche scorrette nel settore assistenza anziani.',
            'sort_order' => 1,
        ],
        [
            'slug' => 'guide',
            'name' => 'Guide',
            'description' => 'How-to, checklist e percorsi guidati per famiglie e caregiver.',
            'sort_order' => 2,
        ],
        [
            'slug' => 'costi',
            'name' => 'Costi',
            'description' => 'Trasparenza su prezzi, rette e costi nascosti di assistenza e strutture.',
            'sort_order' => 3,
        ],
        [
            'slug' => 'diritti',
            'name' => 'Diritti',
            'description' => 'Diritti legali, tutele e informazioni per anziani e famiglie.',
            'sort_order' => 4,
        ],
        [
            'slug' => 'storie',
            'name' => 'Storie di famiglie',
            'description' => 'Esperienze reali di famiglie che affrontano scelte di assistenza.',
            'sort_order' => 5,
        ],
        [
            'slug' => 'interviste',
            'name' => 'Interviste',
            'description' => 'Colloqui con esperti, operatori e consulenti del settore.',
            'sort_order' => 6,
        ],
        [
            'slug' => 'eventi',
            'name' => 'Eventi',
            'description' => 'Open day, webinar e incontri informativi.',
            'sort_order' => 7,
        ],
        [
            'slug' => 'rsa-strutture',
            'name' => 'RSA e strutture',
            'description' => 'Approfondimenti su residenze, case di riposo e strutture assistenziali.',
            'sort_order' => 8,
        ],
    ];

    public function run(): void
    {
        foreach (self::RUBRICS as $rubric) {
            EditorialRubric::query()->updateOrCreate(
                ['slug' => $rubric['slug']],
                [
                    'name' => $rubric['name'],
                    'description' => $rubric['description'],
                    'sort_order' => $rubric['sort_order'],
                    'is_active' => true,
                    'default_index_rules' => [
                        'include_in_sitemap' => true,
                        'include_in_internal_search' => true,
                    ],
                ],
            );
        }
    }
}
