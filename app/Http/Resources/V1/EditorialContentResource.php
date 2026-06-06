<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\EditorialContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditorialContentResource extends JsonResource
{
    public bool $slim = false;

    public bool $forPartner = false;

    public static function slim(mixed $resource): self
    {
        $instance = new self($resource);
        $instance->slim = true;

        return $instance;
    }

    public static function forPartner(mixed $resource): self
    {
        $instance = new self($resource);
        $instance->forPartner = true;

        return $instance;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EditorialContent $content */
        $content = $this->resource;

        if ($this->slim) {
            return [
                'uuid' => $content->uuid,
                'content_type' => $content->content_type?->value,
                'status' => $content->status?->value,
                'title' => $content->title,
                'excerpt' => $content->excerpt,
                'rubric_id' => $content->rubric_id,
                'rubric_slug' => $content->rubric_slug,
                'author_type' => $content->author_type?->value,
                'read_minutes' => $content->read_minutes,
                'featured' => $content->featured,
                'seo_score' => $content->seo_pack['seo_score'] ?? null,
                'updated_at' => $content->updated_at?->toIso8601String(),
            ];
        }

        return [
            'uuid' => $content->uuid,
            'slug' => $content->slug,
            'content_type' => $content->content_type?->value,
            'status' => $content->status?->value,
            'title' => $content->title,
            'subtitle' => $content->subtitle,
            'excerpt' => $content->excerpt,
            'body_blocks' => $content->body_blocks,
            'type_payload' => $content->type_payload,
            'seo_pack' => $content->seo_pack,
            'rubric_id' => $content->rubric_id,
            'rubric_slug' => $content->rubric_slug,
            'rubric' => $this->whenLoaded('rubric', fn () => [
                'id' => $content->rubric?->id,
                'slug' => $content->rubric?->slug,
                'name' => $content->rubric?->name,
            ]),
            'tags' => $content->tags,
            'sector_id' => $content->sector_id,
            'author_type' => $content->author_type?->value,
            'author_name' => $content->author_name,
            'author_role_title' => $content->author_role_title,
            'company_id' => $content->company_id,
            'hero_media_id' => $content->hero_media_id,
            'word_count' => $content->word_count,
            'read_minutes' => $content->read_minutes,
            'featured' => $content->featured,
            'noindex' => $content->noindex,
            'published_at' => $content->published_at?->toIso8601String(),
            'scheduled_at' => $content->scheduled_at?->toIso8601String(),
            'locale' => $content->locale,
            'canonical_path' => $content->canonical_path,
            'created_at' => $content->created_at?->toIso8601String(),
            'updated_at' => $content->updated_at?->toIso8601String(),
            'deleted_at' => $content->deleted_at?->toIso8601String(),
            ...($this->forPartner ? $this->structureMeta($content) : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function structureMeta(EditorialContent $content): array
    {
        return [
            'is_structure_content' => true,
            'author_badge' => $content->company?->organization_name,
            'structure_disclaimer' => (string) config('editorial.structure_disclaimer'),
        ];
    }
}
