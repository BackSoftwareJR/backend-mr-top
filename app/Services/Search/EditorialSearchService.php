<?php

declare(strict_types=1);

namespace App\Services\Search;

use Illuminate\Support\Facades\File;

final class EditorialSearchService
{
    private const DEFAULT_SECTOR = 'elder_care';

    /** @var list<array<string, mixed>>|null */
    private static ?array $cachedIndex = null;

    /**
     * @return list<array{id: string, title: string, summary: string, url: string, relevanceReason: string, sector: string}>
     */
    public function search(?string $query, ?string $sector, int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));
        $normalizedSector = $this->normalizeSector($sector);
        $articles = $this->loadIndex();

        $filtered = array_values(array_filter(
            $articles,
            fn (array $article): bool => $this->matchesSector($article, $normalizedSector),
        ));

        if ($filtered === []) {
            return [];
        }

        $tokens = $this->tokenize($query ?? '');

        if ($tokens === []) {
            return $this->formatResults($this->sortByFeatured($filtered), $limit, []);
        }

        $scored = [];

        foreach ($filtered as $article) {
            $score = $this->scoreArticle($article, $tokens);

            if ($score > 0) {
                $scored[] = ['article' => $article, 'score' => $score, 'matches' => $this->matchedTerms($article, $tokens)];
            }
        }

        if ($scored === []) {
            return $this->formatResults($this->sortByFeatured($filtered), $limit, $tokens);
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        $top = array_map(fn (array $row): array => $row, array_slice($scored, 0, $limit));

        return array_map(
            fn (array $row): array => $this->toResultItem($row['article'], $row['matches']),
            $top,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadIndex(): array
    {
        if (self::$cachedIndex !== null) {
            return self::$cachedIndex;
        }

        $path = database_path('data/editorial_articles.json');

        if (! File::exists($path)) {
            self::$cachedIndex = [];

            return self::$cachedIndex;
        }

        $decoded = json_decode(File::get($path), true);

        self::$cachedIndex = is_array($decoded) ? $decoded : [];

        return self::$cachedIndex;
    }

    private function normalizeSector(?string $sector): string
    {
        $value = mb_strtolower(trim((string) $sector));

        if ($value === '' || $value === 'senior-care' || $value === 'senior_care') {
            return self::DEFAULT_SECTOR;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $article
     */
    private function matchesSector(array $article, string $sector): bool
    {
        $articleSector = mb_strtolower((string) ($article['sector'] ?? self::DEFAULT_SECTOR));

        return $articleSector === $sector
            || ($sector === self::DEFAULT_SECTOR && in_array($articleSector, [self::DEFAULT_SECTOR, 'senior-care', 'senior_care'], true));
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
            fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, $stopWords, true),
        )));
    }

    /**
     * @param  array<string, mixed>  $article
     * @param  list<string>  $tokens
     */
    private function scoreArticle(array $article, array $tokens): int
    {
        $score = 0;
        $haystack = $this->articleHaystack($article);

        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                $score += 10;
            }

            foreach ($article['keywords'] ?? [] as $keyword) {
                $keywordNormalized = mb_strtolower((string) $keyword);

                if ($keywordNormalized === $token || str_contains($keywordNormalized, $token)) {
                    $score += 15;
                }
            }

            $title = mb_strtolower((string) ($article['title'] ?? ''));

            if (str_contains($title, $token)) {
                $score += 20;
            }
        }

        if (($article['featured'] ?? false) === true) {
            $score += 3;
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $article
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function matchedTerms(array $article, array $tokens): array
    {
        $matched = [];
        $haystack = $this->articleHaystack($article);

        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                $matched[] = $token;
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * @param  array<string, mixed>  $article
     */
    private function articleHaystack(array $article): string
    {
        $keywords = implode(' ', array_map('strval', $article['keywords'] ?? []));

        return mb_strtolower(implode(' ', [
            (string) ($article['title'] ?? ''),
            (string) ($article['summary'] ?? ''),
            (string) ($article['category'] ?? ''),
            $keywords,
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $articles
     * @param  list<string>  $tokens
     * @return list<array{id: string, title: string, summary: string, url: string, relevanceReason: string, sector: string}>
     */
    private function formatResults(array $articles, int $limit, array $tokens): array
    {
        return array_map(
            fn (array $article): array => $this->toResultItem($article, $tokens),
            array_slice($articles, 0, $limit),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $articles
     * @return list<array<string, mixed>>
     */
    private function sortByFeatured(array $articles): array
    {
        usort($articles, function (array $a, array $b): int {
            $featuredCompare = ((int) ($b['featured'] ?? false)) <=> ((int) ($a['featured'] ?? false));

            if ($featuredCompare !== 0) {
                return $featuredCompare;
            }

            return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        return $articles;
    }

    /**
     * @param  array<string, mixed>  $article
     * @param  list<string>  $matchedTerms
     * @return array{id: string, title: string, summary: string, url: string, relevanceReason: string, sector: string}
     */
    private function toResultItem(array $article, array $matchedTerms): array
    {
        return [
            'id' => (string) ($article['id'] ?? ''),
            'title' => (string) ($article['title'] ?? ''),
            'summary' => (string) ($article['summary'] ?? ''),
            'url' => (string) ($article['url'] ?? '#'),
            'relevanceReason' => $this->buildRelevanceReason($article, $matchedTerms),
            'sector' => (string) ($article['sector'] ?? self::DEFAULT_SECTOR),
        ];
    }

    /**
     * @param  array<string, mixed>  $article
     * @param  list<string>  $matchedTerms
     */
    private function buildRelevanceReason(array $article, array $matchedTerms): string
    {
        if ($matchedTerms !== []) {
            $terms = implode(', ', array_slice($matchedTerms, 0, 3));

            return "Correlato alla ricerca: {$terms}.";
        }

        $category = trim((string) ($article['category'] ?? ''));

        if ($category !== '') {
            return "Contenuto editoriale · {$category}.";
        }

        return 'Contenuto verificato dal team Wenando.';
    }
}
