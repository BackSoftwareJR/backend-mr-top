<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialContentStatus;
use App\Enums\EditorialContentType;
use App\Models\EditorialContent;
use Illuminate\Database\Eloquent\Builder;

class EditorialContentQueryService
{
    /**
     * @param  array{
     *     type?: string,
     *     rubric?: string,
     *     featured?: bool,
     *     q?: string,
     *     include_noindex?: bool,
     * }  $filters
     */
    public function publishedList(array $filters = []): Builder
    {
        $query = EditorialContent::query()
            ->published()
            ->with(['rubric', 'heroMedia']);

        if (! ($filters['include_noindex'] ?? false)) {
            $query->where('noindex', false);
        }

        if (! empty($filters['type'])) {
            $type = EditorialContentType::tryFrom((string) $filters['type']);
            if ($type !== null) {
                $query->where('content_type', $type);
            }
        }

        if (! empty($filters['rubric'])) {
            $query->where('rubric_slug', (string) $filters['rubric']);
        }

        if (array_key_exists('featured', $filters) && $filters['featured'] !== null) {
            $query->where('featured', (bool) $filters['featured']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.(string) $filters['q'].'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder
                    ->where('title', 'like', $term)
                    ->orWhere('excerpt', 'like', $term);
            });
        }

        return $query->orderByDesc('published_at');
    }

    public function findPublishedBySlug(string $slug): ?EditorialContent
    {
        return EditorialContent::query()
            ->published()
            ->where('noindex', false)
            ->where('slug', $slug)
            ->with(['rubric', 'heroMedia', 'authors.avatarMedia'])
            ->first();
    }

    public function findPublishedByRubricAndSlug(string $rubricSlug, string $slug): ?EditorialContent
    {
        return EditorialContent::query()
            ->published()
            ->where('noindex', false)
            ->where('slug', $slug)
            ->where(function (Builder $query) use ($rubricSlug): void {
                $query
                    ->where('rubric_slug', $rubricSlug)
                    ->orWhereHas('rubric', fn (Builder $rubricQuery) => $rubricQuery->where('slug', $rubricSlug));
            })
            ->with(['rubric', 'heroMedia', 'authors.avatarMedia', 'company'])
            ->first();
    }

    /**
     * @return Builder<EditorialContent>
     */
    public function publishedCountQuery(): Builder
    {
        return EditorialContent::query()
            ->published()
            ->where('noindex', false);
    }
}
