<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EditorialMedia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EditorialMedia>
 */
class EditorialMediaFactory extends Factory
{
    protected $model = EditorialMedia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'disk' => 'public',
            'path' => 'editorial/'.fake()->uuid().'.webp',
            'mime_type' => 'image/webp',
            'width' => 1200,
            'height' => 630,
            'alt_text' => fake()->sentence(4),
            'caption' => fake()->optional()->sentence(),
            'credit' => fake()->optional()->name(),
            'focal_point' => ['x' => 0.5, 'y' => 0.5],
        ];
    }
}
