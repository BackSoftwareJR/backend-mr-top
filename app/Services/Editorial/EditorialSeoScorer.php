<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Models\EditorialContent;
use Illuminate\Support\Str;

class EditorialSeoScorer
{
    /**
     * @param  array<string, mixed>  $seoPack
     * @return array{score: int, breakdown: array<string, int>}
     */
    public function score(EditorialContent $content, array $seoPack): array
    {
        $breakdown = [
            'title_length' => $this->scoreTitleLength($seoPack),
            'description_length' => $this->scoreDescriptionLength($seoPack),
            'keyword_in_title' => $this->scoreKeywordInTitle($seoPack),
            'heading_structure' => $this->scoreHeadingStructure($content),
            'internal_links' => $this->scoreInternalLinks($content),
            'faq_present' => $this->scoreFaqPresent($content),
            'ymyl_disclaimer' => $this->scoreYmylDisclaimer($content),
            'readability' => $this->scoreReadability($content),
            'geo_excerpt_quality' => $this->scoreExcerptQuality($seoPack),
        ];

        $score = (int) round(array_sum($breakdown) / count($breakdown) * 10);

        return [
            'score' => min(100, max(0, $score)),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @param  array<string, mixed>  $seoPack
     */
    private function scoreTitleLength(array $seoPack): int
    {
        $title = trim((string) ($seoPack['seo_title'] ?? ''));
        $length = mb_strlen($title);

        if ($length >= 50 && $length <= 60) {
            return 10;
        }

        if ($length >= 40 && $length <= 70) {
            return 7;
        }

        return $length > 0 ? 4 : 0;
    }

    /**
     * @param  array<string, mixed>  $seoPack
     */
    private function scoreDescriptionLength(array $seoPack): int
    {
        $description = trim((string) ($seoPack['seo_description'] ?? ''));
        $length = mb_strlen($description);

        if ($length >= 150 && $length <= 160) {
            return 10;
        }

        if ($length >= 120 && $length <= 180) {
            return 7;
        }

        return $length > 0 ? 4 : 0;
    }

    /**
     * @param  array<string, mixed>  $seoPack
     */
    private function scoreKeywordInTitle(array $seoPack): int
    {
        $title = mb_strtolower(trim((string) ($seoPack['seo_title'] ?? '')));
        $keyword = mb_strtolower(trim((string) ($seoPack['primary_keyword'] ?? '')));

        if ($title === '' || $keyword === '') {
            return 0;
        }

        return str_contains($title, $keyword) ? 10 : 4;
    }

    private function scoreHeadingStructure(EditorialContent $content): int
    {
        $blocks = $content->body_blocks ?? [];
        $h2Count = 0;

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['type'] ?? '') === 'heading' && (int) ($block['data']['level'] ?? 0) === 2) {
                $h2Count++;
            }
        }

        if ($h2Count >= 3) {
            return 10;
        }

        if ($h2Count >= 1) {
            return 7;
        }

        return 3;
    }

    private function scoreInternalLinks(EditorialContent $content): int
    {
        $blocks = $content->body_blocks ?? [];
        $linkCount = 0;

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $html = (string) ($block['data']['html'] ?? '');

            if ($html !== '' && preg_match_all('/href=["\']\/magazine\//', $html, $matches)) {
                $linkCount += count($matches[0]);
            }
        }

        if ($linkCount >= 2) {
            return 10;
        }

        if ($linkCount >= 1) {
            return 7;
        }

        return 4;
    }

    private function scoreFaqPresent(EditorialContent $content): int
    {
        $blocks = $content->body_blocks ?? [];

        foreach ($blocks as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'faq') {
                return 10;
            }
        }

        return 5;
    }

    private function scoreYmylDisclaimer(EditorialContent $content): int
    {
        $blocks = $content->body_blocks ?? [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['type'] ?? '') === 'callout' && ($block['data']['variant'] ?? '') === 'disclaimer') {
                return 10;
            }
        }

        return 6;
    }

    private function scoreReadability(EditorialContent $content): int
    {
        $wordCount = (int) $content->word_count;

        if ($wordCount >= 800) {
            return 10;
        }

        if ($wordCount >= 300) {
            return 7;
        }

        return 4;
    }

    /**
     * @param  array<string, mixed>  $seoPack
     */
    private function scoreExcerptQuality(array $seoPack): int
    {
        $excerpt = trim((string) ($seoPack['excerpt'] ?? ''));
        $length = mb_strlen($excerpt);

        if ($length >= 120 && $length <= 300) {
            return 10;
        }

        if ($length >= 80) {
            return 7;
        }

        return $length > 0 ? 4 : 0;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public function hasFaqBlocks(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'faq') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, string>>
     */
    public function extractFaqHints(array $blocks): array
    {
        $hints = [];

        foreach ($blocks as $block) {
            if (! is_array($block) || ($block['type'] ?? '') !== 'faq') {
                continue;
            }

            $items = $block['data']['items'] ?? [];

            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $question = trim(strip_tags((string) ($item['question'] ?? '')));
                $answer = trim(strip_tags((string) ($item['answer'] ?? '')));

                if ($question !== '' && $answer !== '') {
                    $hints[] = [
                        'question' => Str::limit($question, 200, ''),
                        'answer' => Str::limit($answer, 500, ''),
                    ];
                }
            }
        }

        return $hints;
    }
}
