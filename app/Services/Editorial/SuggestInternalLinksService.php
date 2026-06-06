<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialContentLinkType;
use App\Models\EditorialContent;
use App\Models\EditorialContentLink;
use Illuminate\Support\Facades\DB;

class SuggestInternalLinksService
{
    private const int MAX_SUGGESTIONS = 5;

    /**
     * @return list<array{
     *     target_uuid: string,
     *     title: string,
     *     slug: string,
     *     rubric_slug: string|null,
     *     score: float,
     *     anchor_text: string|null,
     *     link_type: string
     * }>
     */
    public function suggest(EditorialContent $source, bool $persist = true): array
    {
        $keywords = $this->extractKeywords($source);
        $sourceTags = $this->normalizeTerms($source->tags ?? []);

        $candidates = EditorialContent::query()
            ->published()
            ->where('id', '!=', $source->id)
            ->where('noindex', false)
            ->with(['rubric'])
            ->get();

        $scored = $candidates
            ->map(function (EditorialContent $candidate) use ($source, $keywords, $sourceTags): ?array {
                $score = $this->scoreCandidate($source, $candidate, $keywords, $sourceTags);

                if ($score <= 0) {
                    return null;
                }

                return [
                    'content' => $candidate,
                    'score' => round($score, 4),
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->values()
            ->take(self::MAX_SUGGESTIONS)
            ->all();

        if ($persist) {
            $this->persistSuggestions($source, $scored);
        }

        return array_map(
            fn (array $row): array => $this->formatSuggestion($row['content'], $row['score']),
            $scored,
        );
    }

    /**
     * @return list<array{
     *     target_uuid: string,
     *     title: string,
     *     slug: string,
     *     rubric_slug: string|null,
     *     score: float,
     *     anchor_text: string|null,
     *     link_type: string
     * }>
     */
    public function storedSuggestions(EditorialContent $source): array
    {
        return $source->outgoingLinks()
            ->where('link_type', EditorialContentLinkType::Suggested)
            ->with(['targetContent.rubric'])
            ->orderByDesc('relevance_score')
            ->limit(self::MAX_SUGGESTIONS)
            ->get()
            ->map(function (EditorialContentLink $link): ?array {
                $target = $link->targetContent;

                if ($target === null) {
                    return null;
                }

                return $this->formatSuggestion(
                    $target,
                    (float) ($link->relevance_score ?? 0),
                    $link->anchor_text,
                );
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{content: EditorialContent, score: float}>  $scored
     */
    private function persistSuggestions(EditorialContent $source, array $scored): void
    {
        DB::transaction(function () use ($source, $scored): void {
            EditorialContentLink::query()
                ->where('source_content_id', $source->id)
                ->where('link_type', EditorialContentLinkType::Suggested)
                ->delete();

            foreach ($scored as $row) {
                EditorialContentLink::query()->create([
                    'source_content_id' => $source->id,
                    'target_content_id' => $row['content']->id,
                    'link_type' => EditorialContentLinkType::Suggested,
                    'anchor_text' => null,
                    'relevance_score' => $row['score'],
                ]);
            }
        });
    }

    /**
     * @return array{
     *     target_uuid: string,
     *     title: string,
     *     slug: string,
     *     rubric_slug: string|null,
     *     score: float,
     *     anchor_text: string|null,
     *     link_type: string
     * }
     */
    private function formatSuggestion(
        EditorialContent $target,
        float $score,
        ?string $anchorText = null,
    ): array {
        return [
            'target_uuid' => $target->uuid,
            'title' => $target->title,
            'slug' => $target->slug,
            'rubric_slug' => $target->rubric?->slug ?? $target->rubric_slug,
            'score' => $score,
            'anchor_text' => $anchorText,
            'link_type' => EditorialContentLinkType::Suggested->value,
        ];
    }

    /**
     * @return list<string>
     */
    private function extractKeywords(EditorialContent $source): array
    {
        $terms = $this->normalizeTerms($source->tags ?? []);

        $seoPack = $source->seo_pack ?? [];

        if (isset($seoPack['primary_keyword']) && is_string($seoPack['primary_keyword'])) {
            $terms = array_merge($terms, $this->tokenize($seoPack['primary_keyword']));
        }

        if (isset($seoPack['secondary_keywords']) && is_array($seoPack['secondary_keywords'])) {
            foreach ($seoPack['secondary_keywords'] as $keyword) {
                if (is_string($keyword)) {
                    $terms = array_merge($terms, $this->tokenize($keyword));
                }
            }
        }

        $terms = array_merge($terms, $this->tokenize($source->title));

        return array_values(array_unique(array_filter($terms)));
    }

    /**
     * @param  list<string>  $keywords
     * @param  list<string>  $sourceTags
     */
    private function scoreCandidate(
        EditorialContent $source,
        EditorialContent $candidate,
        array $keywords,
        array $sourceTags,
    ): float {
        $candidateTags = $this->normalizeTerms($candidate->tags ?? []);
        $tagOverlap = $this->overlapRatio($sourceTags, $candidateTags);

        $candidateTerms = array_unique(array_merge(
            $candidateTags,
            $this->tokenize($candidate->title),
            $this->tokenize((string) $candidate->excerpt),
        ));

        $keywordMatches = 0.0;

        foreach ($keywords as $keyword) {
            foreach ($candidateTerms as $term) {
                if ($term === $keyword || str_contains($term, $keyword) || str_contains($keyword, $term)) {
                    $keywordMatches += 1.0;
                    break;
                }
            }
        }

        $keywordScore = $keywords === [] ? 0.0 : min(1.0, $keywordMatches / count($keywords));
        $rubricBonus = ($source->rubric_id !== null && $source->rubric_id === $candidate->rubric_id) ? 0.15 : 0.0;

        $score = (0.55 * $tagOverlap) + (0.45 * $keywordScore) + $rubricBonus;

        return min(1.0, $score);
    }

    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     */
    private function overlapRatio(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }

        $intersection = array_intersect($left, $right);

        if ($intersection === []) {
            return 0.0;
        }

        $union = array_unique(array_merge($left, $right));

        return count($intersection) / max(count($union), 1);
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    private function normalizeTerms(array $values): array
    {
        $terms = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $terms = array_merge($terms, $this->tokenize($value));
        }

        return array_values(array_unique($terms));
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $normalized = mb_strtolower(trim($text));

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
