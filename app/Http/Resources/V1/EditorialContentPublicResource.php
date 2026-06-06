<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\EditorialContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EditorialContentPublicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EditorialContent $content */
        $content = $this->resource;

        $seoPack = is_array($content->seo_pack) ? $content->seo_pack : [];

        return [
            'uuid' => $content->uuid,
            'slug' => $content->slug,
            'content_type' => $content->content_type?->value,
            'title' => $content->title,
            'subtitle' => $content->subtitle,
            'excerpt' => $content->excerpt,
            'body_blocks' => $content->body_blocks,
            'rubric' => $this->whenLoaded('rubric', fn () => [
                'slug' => $content->rubric?->slug,
                'name' => $content->rubric?->name,
            ]),
            'authors' => $this->authorsPayload($content),
            'read_minutes' => $content->read_minutes,
            'word_count' => $content->word_count,
            'published_at' => $content->published_at?->toIso8601String(),
            'updated_at' => $content->updated_at?->toIso8601String(),
            'featured' => $content->featured,
            'url' => $this->magazineUrl($content),
            'hero_image' => $this->heroImageUrl($content),
            'seo' => [
                'title' => $seoPack['title'] ?? $content->title,
                'description' => $seoPack['meta_description'] ?? $seoPack['description'] ?? $content->excerpt,
                'canonical_url' => $content->canonical_path,
                'og_image' => $seoPack['og_image'] ?? $this->heroImageUrl($content),
            ],
        ];
    }

    /**
     * @return list<array{name: string, role: string|null, avatar_url: string|null}>
     */
    private function authorsPayload(EditorialContent $content): array
    {
        if ($content->relationLoaded('authors') && $content->authors->isNotEmpty()) {
            return $content->authors->map(function ($author) {
                $avatarUrl = null;
                if ($author->relationLoaded('avatarMedia') && $author->avatarMedia !== null) {
                    $media = $author->avatarMedia;
                    if ($media->path !== null && $media->path !== '') {
                        $avatarUrl = Storage::disk($media->disk)->url($media->path);
                    }
                }

                return [
                    'name' => $author->display_name,
                    'role' => $author->role_title,
                    'avatar_url' => $avatarUrl,
                ];
            })->all();
        }

        if ($content->author_name !== null && $content->author_name !== '') {
            return [[
                'name' => $content->author_name,
                'role' => $content->author_role_title,
                'avatar_url' => null,
            ]];
        }

        return [];
    }

    private function magazineUrl(EditorialContent $content): string
    {
        $rubricSlug = $content->rubric?->slug ?? $content->rubric_slug ?? 'magazine';

        return '/magazine/'.$rubricSlug.'/'.$content->slug;
    }

    private function heroImageUrl(EditorialContent $content): ?string
    {
        $media = $content->heroMedia;

        if ($media === null || $media->path === null || $media->path === '') {
            return null;
        }

        return Storage::disk($media->disk)->url($media->path);
    }
}
