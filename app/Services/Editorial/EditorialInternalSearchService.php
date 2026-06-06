<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialContentType;
use App\Models\EditorialContent;
use App\Models\EditorialSearchDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EditorialInternalSearchService
{
    /**
     * @return list<EditorialContent>
     */
    public function search(?string $query, ?string $type, ?string $rubric, int $limit = 10): array
    {
        return array_map(
            static fn (array $row): EditorialContent => $row['content'],
            $this->searchRanked($query ?? '', $limit, $type, $rubric),
        );
    }

    /**
     * @return list<array{content: EditorialContent, relevance_score: float}>
     */
    public function searchRanked(string $query, int $limit = 10, ?string $type = null, ?string $rubric = null): array
    {
        $limit = max(1, min($limit, 20));
        $tokens = $this->tokenize($query);

        $documents = $this->baseQuery($type, $rubric)
            ->when($tokens !== [], fn (Builder $builder) => $this->applyTextFilter($builder, $tokens))
            ->with(['content.rubric', 'content.heroMedia'])
            ->limit($tokens === [] ? $limit : 100)
            ->get();

        if ($documents->isEmpty()) {
            return [];
        }

        return $documents
            ->map(function (EditorialSearchDocument $document) use ($tokens, $rubric): ?array {
                $content = $document->content;

                if ($content === null) {
                    return null;
                }

                return [
                    'content' => $content,
                    'relevance_score' => round(
                        $this->scoreDocument($document, $content, $tokens, $rubric),
                        4,
                    ),
                ];
            })
            ->filter()
            ->sortByDesc('relevance_score')
            ->values()
            ->take($limit)
            ->all();
    }

    /**
     * @return Builder<EditorialSearchDocument>
     */
    private function baseQuery(?string $type, ?string $rubric): Builder
    {
        return EditorialSearchDocument::query()
            ->whereHas('content', function (Builder $query) use ($type): void {
                $query->published()->where('noindex', false);

                if ($type !== null && $type !== '') {
                    $query->where('content_type', $type);
                }
            })
            ->when($rubric !== null && $rubric !== '', fn (Builder $query) => $query->where('rubric', $rubric))
            ->whereRaw($this->internalSearchEligibilitySql());
    }

    /**
     * @param  list<string>  $tokens
     */
    private function applyTextFilter(Builder $query, array $tokens): Builder
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $booleanQuery = implode(' ', array_map(
                static fn (string $token): string => '+'.$token.'*',
                $tokens,
            ));

            return $query->whereRaw(
                'MATCH(title, excerpt, body_text) AGAINST (? IN BOOLEAN MODE)',
                [$booleanQuery],
            );
        }

        return $query->where(function (Builder $nested) use ($tokens): void {
            foreach ($tokens as $token) {
                $like = '%'.$token.'%';
                $nested->where(function (Builder $clause) use ($like): void {
                    $clause->where('title', 'like', $like)
                        ->orWhere('excerpt', 'like', $like)
                        ->orWhere('body_text', 'like', $like)
                        ->orWhere('rubric', 'like', $like);
                });
            }
        });
    }

    private function internalSearchEligibilitySql(): string
    {
        return <<<'SQL'
CASE
    WHEN EXISTS (
        SELECT 1 FROM editorial_index_rules r
        WHERE r.rubric_slug = editorial_search_documents.rubric
          AND r.is_active = 1
    ) THEN (
        SELECT r.include_in_internal_search FROM editorial_index_rules r
        WHERE r.rubric_slug = editorial_search_documents.rubric
          AND r.is_active = 1
        LIMIT 1
    )
    WHEN EXISTS (
        SELECT 1 FROM editorial_index_rules g
        WHERE g.rubric_slug IS NULL
          AND g.is_active = 1
    ) THEN (
        SELECT g.include_in_internal_search FROM editorial_index_rules g
        WHERE g.rubric_slug IS NULL
          AND g.is_active = 1
        LIMIT 1
    )
    ELSE 1
END = 1
SQL;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function scoreDocument(
        EditorialSearchDocument $document,
        EditorialContent $content,
        array $tokens,
        ?string $rubricFilter,
    ): float {
        $textRelevance = $tokens === []
            ? 0.35
            : $this->textRelevanceScore($document, $tokens);

        $titleExactBoost = $this->titleExactBoost($document->title, $tokens);
        $rubricMatch = ($rubricFilter !== null && $rubricFilter !== '' && $document->rubric === $rubricFilter) ? 1.0 : 0.0;
        $recencyBoost = $this->recencyBoost($document->published_at);
        $featuredBoost = $content->featured ? 1.0 : 0.0;
        $typeWeight = $this->typeWeight($content->content_type);

        return (
            (0.40 * $textRelevance)
            + (0.20 * $titleExactBoost)
            + (0.15 * $rubricMatch)
            + (0.10 * $recencyBoost)
            + (0.10 * $featuredBoost)
            + (0.05 * $typeWeight)
        );
    }

    /**
     * @param  list<string>  $tokens
     */
    private function textRelevanceScore(EditorialSearchDocument $document, array $tokens): float
    {
        if ($tokens === []) {
            return 0.0;
        }

        $title = mb_strtolower($document->title);
        $excerpt = mb_strtolower((string) $document->excerpt);
        $body = mb_strtolower((string) $document->body_text);
        $tags = implode(' ', array_map('strval', $document->tags ?? []));
        $tags = mb_strtolower($tags);

        $matches = 0.0;

        foreach ($tokens as $token) {
            if (str_contains($title, $token)) {
                $matches += 1.0;
            } elseif (str_contains($excerpt, $token)) {
                $matches += 0.7;
            } elseif (str_contains($body, $token)) {
                $matches += 0.4;
            } elseif (str_contains($tags, $token)) {
                $matches += 0.5;
            }
        }

        return min(1.0, $matches / max(count($tokens), 1));
    }

    /**
     * @param  list<string>  $tokens
     */
    private function titleExactBoost(string $title, array $tokens): float
    {
        if ($tokens === []) {
            return 0.0;
        }

        $normalizedTitle = mb_strtolower(trim($title));
        $normalizedQuery = mb_strtolower(trim(implode(' ', $tokens)));

        if ($normalizedTitle === $normalizedQuery) {
            return 1.0;
        }

        foreach ($tokens as $token) {
            if (str_contains($normalizedTitle, $token)) {
                return 0.75;
            }
        }

        return 0.0;
    }

    private function recencyBoost(?\Illuminate\Support\Carbon $publishedAt): float
    {
        if ($publishedAt === null) {
            return 0.0;
        }

        $days = max(0, $publishedAt->diffInDays(now()));

        return 1.0 / (1.0 + ($days / 30.0));
    }

    private function typeWeight(?EditorialContentType $type): float
    {
        return match ($type) {
            EditorialContentType::Article => 1.0,
            EditorialContentType::Interview => 0.9,
            EditorialContentType::Story => 0.85,
            EditorialContentType::Event => 0.75,
            default => 0.8,
        };
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $query): array
    {
        $normalized = mb_strtolower(trim($query));

        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        if (! is_array($parts)) {
            return [];
        }

        $stopWords = ['per', 'con', 'una', 'uno', 'dei', 'delle', 'che', 'non', 'sono', 'mio', 'mia', 'del', 'della'];

        return array_values(array_unique(array_filter(
            $parts,
            static fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, $stopWords, true),
        )));
    }
}
