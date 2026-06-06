<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentStatus;
use App\Enums\EditorialContentType;
use App\Models\EditorialContent;
use App\Models\EditorialMedia;
use App\Models\EditorialRubric;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EditorialContent>
 */
class EditorialContentFactory extends Factory
{
    protected $model = EditorialContent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(6);
        $slug = Str::slug(Str::limit($title, 80, ''));

        return [
            'uuid' => (string) Str::uuid(),
            'slug' => $slug.'-'.fake()->unique()->numerify('###'),
            'content_type' => EditorialContentType::Article,
            'status' => EditorialContentStatus::Draft,
            'title' => $title,
            'subtitle' => fake()->optional()->sentence(10),
            'excerpt' => fake()->optional()->paragraph(),
            'body_blocks' => [
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'heading',
                    'data' => [
                        'level' => 2,
                        'text' => 'Introduzione',
                        'anchor' => 'introduzione',
                    ],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'paragraph',
                    'data' => [
                        'html' => '<p>'.fake()->paragraph().'</p>',
                    ],
                ],
            ],
            'type_payload' => null,
            'seo_pack' => null,
            'rubric_slug' => 'guide',
            'tags' => ['assistenza', 'anziani'],
            'sector_id' => Sector::query()->value('id') ?? 1,
            'author_type' => EditorialAuthorType::Admin,
            'author_name' => fake()->name(),
            'author_role_title' => 'Redazione Wenando',
            'word_count' => 120,
            'read_minutes' => 1,
            'featured' => false,
            'noindex' => false,
            'locale' => 'it-IT',
            'canonical_path' => '/magazine/articoli/'.$slug,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => EditorialContentStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }

    public function withRubric(EditorialRubric $rubric): static
    {
        return $this->state(fn () => [
            'rubric_id' => $rubric->id,
            'rubric_slug' => $rubric->slug,
        ]);
    }

    public function withHeroMedia(EditorialMedia $media): static
    {
        return $this->state(fn () => [
            'hero_media_id' => $media->id,
        ]);
    }
}
