<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Models\EditorialIndexRule;
use App\Models\EditorialRubric;
use Database\Seeders\EditorialPermissionSeeder;
use Database\Seeders\EditorialRubricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialRubricsSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const EXPECTED_SLUGS = [
        'anti-truffe',
        'guide',
        'costi',
        'diritti',
        'storie',
        'interviste',
        'eventi',
        'rsa-strutture',
    ];

    public function test_rubric_seeder_creates_eight_active_rubrics(): void
    {
        $this->seed(EditorialRubricSeeder::class);

        $this->assertSame(8, EditorialRubric::query()->count());

        foreach (self::EXPECTED_SLUGS as $slug) {
            $this->assertDatabaseHas('editorial_rubrics', [
                'slug' => $slug,
                'is_active' => true,
            ]);
        }

        $antiTruffe = EditorialRubric::query()->where('slug', 'anti-truffe')->firstOrFail();
        $this->assertSame('Anti-truffe', $antiTruffe->name);
        $this->assertIsArray($antiTruffe->default_index_rules);
        $this->assertTrue($antiTruffe->default_index_rules['include_in_sitemap']);
    }

    public function test_permission_seeder_creates_global_index_rule(): void
    {
        $this->seed(EditorialPermissionSeeder::class);

        $this->assertDatabaseHas('editorial_index_rules', [
            'rubric_slug' => null,
            'include_in_sitemap' => true,
            'include_in_internal_search' => true,
            'is_active' => true,
        ]);

        $this->assertSame(1, EditorialIndexRule::query()->whereNull('rubric_slug')->count());
    }
}
