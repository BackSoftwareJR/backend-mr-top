<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialContentStatus;
use App\Models\EditorialContent;
use App\Models\EditorialIndexRule;
use App\Models\EditorialSearchDocument;

class EditorialSearchIndexer
{
    public function index(EditorialContent $content): void
    {
        $content->loadMissing('rubric');

        if (! $this->shouldIndex($content)) {
            $this->remove($content->uuid);

            return;
        }

        EditorialSearchDocument::query()->updateOrCreate(
            ['content_id' => $content->id],
            [
                'title' => $content->title,
                'excerpt' => $content->excerpt,
                'body_text' => $this->extractPlainText($content->body_blocks ?? []),
                'rubric' => $content->rubric_slug ?? $content->rubric?->slug,
                'tags' => $content->tags,
                'content_type' => $content->content_type?->value,
                'company_id' => $content->company_id,
                'published_at' => $content->published_at,
                'indexed_at' => now(),
            ],
        );
    }

    public function remove(string $contentUuid): void
    {
        $contentId = EditorialContent::query()
            ->where('uuid', $contentUuid)
            ->value('id');

        if ($contentId === null) {
            return;
        }

        EditorialSearchDocument::query()
            ->where('content_id', $contentId)
            ->delete();
    }

    public function shouldIndex(EditorialContent $content): bool
    {
        if ($content->noindex) {
            return false;
        }

        if ($content->status !== EditorialContentStatus::Published) {
            return false;
        }

        if ($content->published_at === null || $content->published_at->isFuture()) {
            return false;
        }

        return $this->isIncludedInInternalSearch($content->rubric_slug ?? $content->rubric?->slug);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public function extractPlainText(array $blocks): string
    {
        $parts = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? '');
            $data = $block['data'] ?? [];

            if (! is_array($data)) {
                continue;
            }

            $parts = array_merge($parts, match ($type) {
                'heading' => [strip_tags((string) ($data['text'] ?? ''))],
                'paragraph' => [strip_tags((string) ($data['html'] ?? $data['text'] ?? ''))],
                'callout' => [strip_tags((string) ($data['body'] ?? $data['text'] ?? ''))],
                'faq' => $this->extractFaqText($data),
                'quote' => [strip_tags((string) ($data['text'] ?? ''))],
                'list' => $this->extractListText($data),
                'layout' => app(EditorialLayoutRenderer::class)->extractPlainTextFromSlots(
                    is_array($data['slots'] ?? null) ? $data['slots'] : [],
                ),
                default => [],
            });
        }

        return trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter($parts))) ?? '');
    }

    private function isIncludedInInternalSearch(?string $rubricSlug): bool
    {
        if ($rubricSlug !== null && $rubricSlug !== '') {
            $rubricRule = EditorialIndexRule::query()
                ->where('rubric_slug', $rubricSlug)
                ->where('is_active', true)
                ->first();

            if ($rubricRule !== null) {
                return $rubricRule->include_in_internal_search;
            }
        }

        $globalRule = EditorialIndexRule::query()
            ->whereNull('rubric_slug')
            ->where('is_active', true)
            ->first();

        return $globalRule?->include_in_internal_search ?? true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function extractFaqText(array $data): array
    {
        $items = $data['items'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        $parts = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $parts[] = strip_tags((string) ($item['question'] ?? ''));
            $parts[] = strip_tags((string) ($item['answer'] ?? ''));
        }

        return $parts;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function extractListText(array $data): array
    {
        $items = $data['items'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        return array_map(
            static fn (mixed $item): string => strip_tags(is_string($item) ? $item : (string) ($item['text'] ?? '')),
            $items,
        );
    }
}
