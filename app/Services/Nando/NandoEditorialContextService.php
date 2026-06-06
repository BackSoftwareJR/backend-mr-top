<?php

declare(strict_types=1);

namespace App\Services\Nando;

use App\Models\EditorialContent;
use App\Services\Editorial\EditorialInternalSearchService;

class NandoEditorialContextService
{
    public function __construct(
        private readonly EditorialInternalSearchService $editorialSearch,
    ) {}

    /**
     * @return list<array{title: string, excerpt: string, url: string, rubric: string, type: string, relevance_score: float}>
     */
    public function topSnippets(string $query, int $limit = 5): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $limit = max(1, min($limit, 20));

        return array_map(
            fn (array $row): array => $this->toSnippet($row['content'], $row['relevance_score']),
            $this->editorialSearch->searchRanked($query, $limit),
        );
    }

    /**
     * @return array{title: string, excerpt: string, url: string, rubric: string, type: string, relevance_score: float}
     */
    private function toSnippet(EditorialContent $content, float $relevanceScore): array
    {
        return [
            'title' => $content->title,
            'excerpt' => (string) ($content->excerpt ?? ''),
            'url' => $this->magazineUrl($content),
            'rubric' => $content->rubric?->slug ?? $content->rubric_slug ?? '',
            'type' => $content->content_type?->value ?? '',
            'relevance_score' => $relevanceScore,
        ];
    }

    private function magazineUrl(EditorialContent $content): string
    {
        $rubricSlug = $content->rubric?->slug ?? $content->rubric_slug ?? 'magazine';

        return '/magazine/'.$rubricSlug.'/'.$content->slug;
    }
}
