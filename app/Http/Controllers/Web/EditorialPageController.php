<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EditorialContent;
use App\Services\Editorial\EditorialBlockRenderer;
use App\Services\Editorial\EditorialContentQueryService;
use App\Services\Editorial\EditorialJsonLdBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class EditorialPageController extends Controller
{
    public function __construct(
        private readonly EditorialContentQueryService $queryService,
        private readonly EditorialBlockRenderer $blockRenderer,
        private readonly EditorialJsonLdBuilder $jsonLdBuilder,
    ) {}

    public function hub(): View
    {
        $contents = $this->queryService
            ->publishedList()
            ->limit(24)
            ->get();

        return view('editorial.hub', [
            'contents' => $contents,
        ]);
    }

    public function show(string $rubricSlug, string $slug): View|Response
    {
        $content = $this->queryService->findPublishedByRubricAndSlug($rubricSlug, $slug);

        if ($content === null) {
            abort(404);
        }

        return view('editorial.show', $this->pageViewData($content));
    }

    /**
     * @return array<string, mixed>
     */
    private function pageViewData(EditorialContent $content): array
    {
        $seoPack = is_array($content->seo_pack) ? $content->seo_pack : [];
        $rendered = $this->blockRenderer->render($content->body_blocks);
        $heroImageUrl = $this->jsonLdBuilder->heroImageUrl($content);
        $canonicalUrl = $this->jsonLdBuilder->canonicalUrl($content);

        $jsonLd = $this->jsonLdBuilder->build(
            $content,
            $rendered['faq_items'],
            $canonicalUrl,
            $heroImageUrl,
        );

        $geoExcerpt = trim((string) ($seoPack['geo_excerpt'] ?? $content->excerpt ?? ''));

        return [
            'content' => $content,
            'seoTitle' => $this->jsonLdBuilder->resolveSeoTitle($content, $seoPack),
            'metaDescription' => $this->jsonLdBuilder->resolvePageMetaDescription($content, $seoPack),
            'canonicalUrl' => $canonicalUrl,
            'ogImage' => $this->jsonLdBuilder->resolveOgImage($seoPack, $heroImageUrl),
            'geoExcerpt' => $geoExcerpt,
            'bodyHtml' => $rendered['html'],
            'toc' => $rendered['toc'],
            'jsonLd' => $jsonLd,
            'structureDisclaimer' => $content->company_id !== null
                ? (string) config('editorial.structure_disclaimer')
                : null,
            'authorName' => $this->resolveAuthorName($content),
            'authorRole' => $this->resolveAuthorRole($content),
        ];
    }

    private function resolveAuthorName(EditorialContent $content): ?string
    {
        if ($content->relationLoaded('authors') && $content->authors->isNotEmpty()) {
            return $content->authors->first()->display_name;
        }

        return $content->author_name;
    }

    private function resolveAuthorRole(EditorialContent $content): ?string
    {
        if ($content->relationLoaded('authors') && $content->authors->isNotEmpty()) {
            return $content->authors->first()->role_title;
        }

        return $content->author_role_title;
    }
}
