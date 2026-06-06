<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentStatus;
use App\Models\EditorialContent;
use App\Models\EditorialContentRevision;
use App\Models\EditorialRubric;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class EditorialContentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): EditorialContent
    {
        $rubric = EditorialRubric::query()->findOrFail($data['rubric_id']);
        $title = (string) $data['title'];
        $authorType = isset($data['author_type'])
            ? EditorialAuthorType::from($data['author_type'])
            : EditorialAuthorType::Admin;

        $content = EditorialContent::query()->create([
            'slug' => $this->uniqueSlug($title),
            'content_type' => $data['content_type'],
            'status' => EditorialContentStatus::Draft,
            'title' => $title,
            'subtitle' => $data['subtitle'] ?? null,
            'excerpt' => $data['excerpt'] ?? null,
            'body_blocks' => $data['body_blocks'],
            'type_payload' => $data['type_payload'] ?? null,
            'seo_pack' => $data['seo_pack'] ?? null,
            'rubric_id' => $rubric->id,
            'rubric_slug' => $rubric->slug,
            'tags' => $data['tags'] ?? null,
            'sector_id' => $data['sector_id'] ?? Sector::query()->where('is_active', true)->value('id'),
            'author_type' => $authorType,
            'author_name' => $data['author_name'] ?? null,
            'author_role_title' => $data['author_role_title'] ?? null,
            'company_id' => $authorType === EditorialAuthorType::Company ? ($data['company_id'] ?? null) : null,
            'featured' => (bool) ($data['featured'] ?? false),
            'noindex' => (bool) ($data['noindex'] ?? false),
            'locale' => $data['locale'] ?? 'it-IT',
            'canonical_path' => $data['canonical_path'] ?? null,
            ...$this->metricsFromBlocks($data['body_blocks']),
        ]);

        return $content->fresh(['rubric']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EditorialContent $content, array $data, User $user): EditorialContent
    {
        $this->createRevisionSnapshot($content, $user, 'Auto-snapshot before update');

        if (isset($data['rubric_id'])) {
            $rubric = EditorialRubric::query()->findOrFail($data['rubric_id']);
            $data['rubric_slug'] = $rubric->slug;
        }

        if (isset($data['title']) && ! isset($data['slug'])) {
            $data['slug'] = $this->uniqueSlug((string) $data['title'], $content->id);
        }

        if (isset($data['author_type'])) {
            $authorType = EditorialAuthorType::from($data['author_type']);
            if ($authorType !== EditorialAuthorType::Company) {
                $data['company_id'] = null;
            }
        }

        if (isset($data['body_blocks'])) {
            $data = array_merge($data, $this->metricsFromBlocks($data['body_blocks']));
        }

        $content->update(Arr::only($data, [
            'slug',
            'content_type',
            'title',
            'subtitle',
            'excerpt',
            'body_blocks',
            'type_payload',
            'seo_pack',
            'rubric_id',
            'rubric_slug',
            'tags',
            'sector_id',
            'author_type',
            'author_name',
            'author_role_title',
            'company_id',
            'featured',
            'noindex',
            'locale',
            'canonical_path',
            'word_count',
            'read_minutes',
        ]));

        return $content->fresh(['rubric']);
    }

    public function createRevisionSnapshot(
        EditorialContent $content,
        User $user,
        ?string $changeSummary = null,
    ): EditorialContentRevision {
        $revisionNumber = (int) $content->revisions()->max('revision_number') + 1;

        return $content->revisions()->create([
            'revision_number' => $revisionNumber,
            'snapshot' => $this->buildSnapshot($content),
            'body_blocks' => $content->body_blocks,
            'seo_pack' => $content->seo_pack,
            'created_by_user_id' => $user->id,
            'change_summary' => $changeSummary,
        ]);
    }

    public function delete(EditorialContent $content): void
    {
        $content->delete();
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($title, 80, ''));
        if ($base === '') {
            $base = 'contenuto';
        }

        $slug = $base;
        $suffix = 1;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId): bool
    {
        return EditorialContent::query()
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(EditorialContent $content): array
    {
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
            'tags' => $content->tags,
            'sector_id' => $content->sector_id,
            'author_type' => $content->author_type?->value,
            'author_name' => $content->author_name,
            'author_role_title' => $content->author_role_title,
            'company_id' => $content->company_id,
            'featured' => $content->featured,
            'noindex' => $content->noindex,
            'locale' => $content->locale,
            'canonical_path' => $content->canonical_path,
            'updated_at' => $content->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{word_count: int, read_minutes: int}
     */
    private function metricsFromBlocks(array $blocks): array
    {
        $text = collect($blocks)
            ->map(function (array $block): string {
                $data = $block['data'] ?? [];

                return match ($block['type'] ?? '') {
                    'heading' => (string) ($data['text'] ?? ''),
                    'paragraph' => strip_tags((string) ($data['html'] ?? $data['text'] ?? '')),
                    'callout' => strip_tags((string) ($data['body'] ?? $data['text'] ?? $data['html'] ?? '')),
                    'layout' => implode(' ', app(EditorialLayoutRenderer::class)->extractPlainTextFromSlots(
                        is_array($data['slots'] ?? null) ? $data['slots'] : [],
                    )),
                    default => '',
                };
            })
            ->implode(' ');

        $wordCount = str_word_count($text);

        return [
            'word_count' => $wordCount,
            'read_minutes' => max(1, (int) ceil($wordCount / 200)),
        ];
    }
}
