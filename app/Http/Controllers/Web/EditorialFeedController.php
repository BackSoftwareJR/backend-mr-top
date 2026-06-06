<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\EditorialContentType;
use App\Http\Controllers\Controller;
use App\Models\EditorialContent;
use App\Services\Editorial\EditorialContentQueryService;
use App\Services\Editorial\EditorialJsonLdBuilder;
use App\Services\Editorial\EditorialLlmsTxtService;
use App\Services\Editorial\EditorialSitemapService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

class EditorialFeedController extends Controller
{
    public function __construct(
        private readonly EditorialContentQueryService $queryService,
        private readonly EditorialJsonLdBuilder $jsonLdBuilder,
        private readonly EditorialSitemapService $sitemapService,
        private readonly EditorialLlmsTxtService $llmsTxtService,
    ) {}

    public function rss(): Response
    {
        $siteUrl = rtrim((string) config('editorial.site_url', config('app.url')), '/');

        $items = $this->queryService
            ->publishedList(['type' => EditorialContentType::Article->value])
            ->limit(50)
            ->get();

        $channelItems = $items->map(function (EditorialContent $content) use ($siteUrl): string {
            $link = $this->jsonLdBuilder->canonicalUrl($content);
            $seoPack = is_array($content->seo_pack) ? $content->seo_pack : [];
            $description = $this->jsonLdBuilder->resolvePageMetaDescription($content, $seoPack);
            $heroImageUrl = $this->jsonLdBuilder->heroImageUrl($content);
            $category = $content->rubric?->name ?? $content->rubric_slug ?? 'Magazine';
            $pubDate = ($content->published_at ?? now())->toRfc2822String();

            $enclosure = '';
            if ($heroImageUrl !== null && $content->heroMedia !== null) {
                $enclosure = sprintf(
                    "\n      <enclosure url=\"%s\" length=\"0\" type=\"%s\" />",
                    $this->escapeXml($heroImageUrl),
                    $this->escapeXml($content->heroMedia->mime_type ?? 'image/jpeg'),
                );
            }

            return sprintf(
                "    <item>\n      <title>%s</title>\n      <link>%s</link>\n      <guid isPermaLink=\"true\">%s</guid>\n      <description>%s</description>\n      <pubDate>%s</pubDate>\n      <category>%s</category>%s\n    </item>",
                $this->escapeXml($content->title),
                $this->escapeXml($link),
                $this->escapeXml($link),
                $this->escapeXml($description),
                $this->escapeXml($pubDate),
                $this->escapeXml((string) $category),
                $enclosure,
            );
        })->implode("\n");

        $lastBuild = now()->toRfc2822String();

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>Wenando Magazine</title>
    <link>{$this->escapeXml($siteUrl)}/magazine</link>
    <description>Articoli e guide Wenando su assistenza anziani, RSA e badanti in Italia.</description>
    <language>it-IT</language>
    <lastBuildDate>{$this->escapeXml($lastBuild)}</lastBuildDate>
    <atom:link href="{$this->escapeXml($siteUrl)}/feed.xml" rel="self" type="application/rss+xml" />
{$channelItems}
  </channel>
</rss>
XML;

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }

    public function robots(): Response
    {
        $siteUrl = rtrim((string) config('editorial.site_url', config('app.url')), '/');

        $body = <<<TXT
User-agent: *
Allow: /magazine/
Disallow: /api/

User-agent: GPTBot
Allow: /magazine/

User-agent: ChatGPT-User
Allow: /magazine/

User-agent: Google-Extended
Allow: /magazine/

Sitemap: {$siteUrl}/sitemap.xml
TXT;

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function llms(): Response
    {
        $content = $this->llmsTxtService->read();

        if ($content === null) {
            $content = $this->llmsTxtService->generate();
        }

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function sitemap(): Response
    {
        return $this->serveSitemapFile('sitemap.xml');
    }

    public function sitemapIndex(): Response
    {
        return $this->serveSitemapFile('sitemap-index.xml');
    }

    public function sitemapChunk(int $chunk): Response
    {
        return $this->serveSitemapFile('sitemap-'.$chunk.'.xml');
    }

    private function serveSitemapFile(string $filename): Response
    {
        $xml = $this->sitemapService->readFile($filename);

        if ($xml === null) {
            Artisan::call('editorial:generate-sitemaps');
            $xml = $this->sitemapService->readFile($filename);
        }

        if ($xml === null) {
            abort(404);
        }

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
