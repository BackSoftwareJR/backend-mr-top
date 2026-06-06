<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\EditorialContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EditorialContentCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EditorialContent $content */
        $content = $this->resource;

        $card = [
            'id' => $content->uuid,
            'type' => $content->content_type?->value,
            'title' => $content->title,
            'description' => $content->excerpt,
            'category' => $content->rubric?->name ?? $content->rubric_slug,
            'readMinutes' => $content->read_minutes,
            'url' => $this->magazineUrl($content),
            'image' => $this->heroImageUrl($content),
        ];

        if ($content->featured) {
            $card['featured'] = true;
        }

        return $card;
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
