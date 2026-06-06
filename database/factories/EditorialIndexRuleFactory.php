<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EditorialIndexRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditorialIndexRule>
 */
class EditorialIndexRuleFactory extends Factory
{
    protected $model = EditorialIndexRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rubric_slug' => null,
            'include_in_sitemap' => true,
            'include_in_internal_search' => true,
            'noindex_default' => false,
            'exclude_from_crawl' => false,
            'is_active' => true,
        ];
    }
}
