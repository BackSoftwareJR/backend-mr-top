<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialContentType;
use App\Models\EditorialContent;
use Illuminate\Support\Facades\Storage;

class EditorialJsonLdBuilder
{
    /**
     * @param  list<array{question: string, answer: string}>  $faqItems
     * @return array<string, mixed>
     */
    public function build(
        EditorialContent $content,
        array $faqItems,
        string $canonicalUrl,
        ?string $heroImageUrl,
    ): array {
        $siteUrl = rtrim((string) config('editorial.site_url', config('app.url')), '/');
        $organizationId = $siteUrl.'/#organization';
        $websiteId = $siteUrl.'/#website';
        $articleId = $canonicalUrl.'#article';

        $graph = [
            [
                '@type' => 'Organization',
                '@id' => $organizationId,
                'name' => 'Wenando',
                'url' => $siteUrl,
                'logo' => $siteUrl.'/logo.png',
            ],
            [
                '@type' => 'WebSite',
                '@id' => $websiteId,
                'url' => $siteUrl,
                'publisher' => ['@id' => $organizationId],
            ],
        ];

        $mainEntity = $this->buildMainEntity(
            $content,
            $articleId,
            $canonicalUrl,
            $heroImageUrl,
            $organizationId,
        );

        if ($mainEntity !== null) {
            $graph[] = $mainEntity;
        }

        $graph[] = $this->buildBreadcrumbs($content, $siteUrl, $canonicalUrl);

        if ($faqItems !== []) {
            $graph[] = $this->buildFaqPage($faqItems);
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildMainEntity(
        EditorialContent $content,
        string $articleId,
        string $canonicalUrl,
        ?string $heroImageUrl,
        string $organizationId,
    ): ?array {
        $seoPack = is_array($content->seo_pack) ? $content->seo_pack : [];
        $description = $this->resolvePageMetaDescription($content, $seoPack);
        $author = $this->resolveAuthor($content);

        $base = [
            '@id' => $articleId,
            'headline' => $content->title,
            'description' => $description,
            'author' => $author,
            'publisher' => ['@id' => $organizationId],
            'datePublished' => $content->published_at?->toIso8601String(),
            'dateModified' => ($content->updated_at ?? $content->published_at)?->toIso8601String(),
            'mainEntityOfPage' => $canonicalUrl,
            'inLanguage' => $content->locale ?? 'it-IT',
        ];

        if ($heroImageUrl !== null) {
            $base['image'] = [$heroImageUrl];
        }

        return match ($content->content_type) {
            EditorialContentType::Event => array_merge($base, [
                '@type' => 'Event',
                'name' => $content->title,
                'url' => $canonicalUrl,
                ...$this->eventPayload($content),
            ]),
            EditorialContentType::Story => array_merge($base, [
                '@type' => 'BlogPosting',
            ]),
            default => array_merge($base, [
                '@type' => 'Article',
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAuthor(EditorialContent $content): array
    {
        if ($content->relationLoaded('authors') && $content->authors->isNotEmpty()) {
            $author = $content->authors->first();

            return array_filter([
                '@type' => 'Person',
                'name' => $author->display_name,
                'jobTitle' => $author->role_title,
            ]);
        }

        if ($content->author_name !== null && $content->author_name !== '') {
            return array_filter([
                '@type' => 'Person',
                'name' => $content->author_name,
                'jobTitle' => $content->author_role_title,
            ]);
        }

        return [
            '@type' => 'Organization',
            'name' => 'Wenando',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBreadcrumbs(EditorialContent $content, string $siteUrl, string $canonicalUrl): array
    {
        $rubricName = $content->rubric?->name ?? $content->rubric_slug ?? 'Magazine';
        $rubricSlug = $content->rubric?->slug ?? $content->rubric_slug ?? 'magazine';

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Magazine',
                    'item' => $siteUrl.'/magazine',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $rubricName,
                    'item' => $siteUrl.'/magazine/'.$rubricSlug,
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $content->title,
                    'item' => $canonicalUrl,
                ],
            ],
        ];
    }

    /**
     * @param  list<array{question: string, answer: string}>  $faqItems
     * @return array<string, mixed>
     */
    private function buildFaqPage(array $faqItems): array
    {
        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $item) => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ], $faqItems),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(EditorialContent $content): array
    {
        $payload = is_array($content->type_payload) ? $content->type_payload : [];

        return array_filter([
            'startDate' => $payload['start_date'] ?? $payload['startDate'] ?? null,
            'endDate' => $payload['end_date'] ?? $payload['endDate'] ?? null,
            'eventAttendanceMode' => $payload['event_attendance_mode'] ?? $payload['eventAttendanceMode'] ?? null,
            'location' => isset($payload['location']) && is_array($payload['location'])
                ? $payload['location']
                : null,
        ]);
    }

    public function heroImageUrl(EditorialContent $content): ?string
    {
        $media = $content->heroMedia;

        if ($media === null || $media->path === null || $media->path === '') {
            return null;
        }

        return Storage::disk($media->disk)->url($media->path);
    }

    public function canonicalUrl(EditorialContent $content): string
    {
        if ($content->canonical_path !== null && str_starts_with($content->canonical_path, 'http')) {
            return $content->canonical_path;
        }

        $siteUrl = rtrim((string) config('editorial.site_url', config('app.url')), '/');

        return $siteUrl.$this->magazinePath($content);
    }

    public function magazinePath(EditorialContent $content): string
    {
        $rubricSlug = $content->rubric?->slug ?? $content->rubric_slug ?? 'magazine';

        return '/magazine/'.$rubricSlug.'/'.$content->slug;
    }

    /**
     * @param  array<string, mixed>  $seoPack
     */
    public function resolveSeoTitle(EditorialContent $content, array $seoPack): string
    {
        $title = trim((string) (
            $seoPack['manual_overrides']['seo_title']
            ?? $seoPack['seo_title']
            ?? $content->title
        ));

        return $title;
    }

    /**
     * @param  array<string, mixed>  $seoPack
     */
    public function resolvePageMetaDescription(EditorialContent $content, array $seoPack): string
    {
        return trim((string) (
            $seoPack['manual_overrides']['meta_description']
            ?? $seoPack['meta_description']
            ?? $seoPack['seo_description']
            ?? $seoPack['geo_excerpt']
            ?? $content->excerpt
            ?? ''
        ));
    }

    /**
     * @param  array<string, mixed>  $seoPack
     */
    public function resolveOgImage(array $seoPack, ?string $heroImageUrl): ?string
    {
        $ogImage = $seoPack['og_image'] ?? null;

        if (is_string($ogImage) && $ogImage !== '') {
            return $ogImage;
        }

        return $heroImageUrl;
    }
}
