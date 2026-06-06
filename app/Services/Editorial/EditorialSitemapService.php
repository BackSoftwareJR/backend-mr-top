<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialContentType;
use App\Models\EditorialContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class EditorialSitemapService
{
    private const CHUNK_SIZE = 1000;

    private const STORAGE_DIR = 'editorial';

    public function __construct(
        private readonly EditorialJsonLdBuilder $jsonLdBuilder,
    ) {}

    /**
     * @return Builder<EditorialContent>
     */
    public function sitemapEligibleQuery(): Builder
    {
        return EditorialContent::query()
            ->published()
            ->where('noindex', false)
            ->whereRaw($this->sitemapEligibilitySql())
            ->orderByDesc('updated_at');
    }

    /**
     * @return array{url_count: int, files: list<string>, chunked: bool}
     */
    public function generate(): array
    {
        $siteUrl = rtrim((string) config('editorial.site_url', config('app.url')), '/');
        $contents = $this->sitemapEligibleQuery()
            ->with(['rubric'])
            ->get();

        $entries = $contents->map(fn (EditorialContent $content) => $this->entryForContent($content, $siteUrl));

        $disk = Storage::disk('public');
        $disk->makeDirectory(self::STORAGE_DIR);

        foreach ($disk->files(self::STORAGE_DIR) as $existing) {
            if (preg_match('#^'.preg_quote(self::STORAGE_DIR, '#').'/sitemap(-index|-\d+)?\.xml$#', $existing)) {
                $disk->delete($existing);
            }
        }

        $files = [];

        if ($entries->count() <= self::CHUNK_SIZE) {
            $path = self::STORAGE_DIR.'/sitemap.xml';
            $disk->put($path, $this->buildUrlSet($entries));
            $files[] = $path;

            return [
                'url_count' => $entries->count(),
                'files' => $files,
                'chunked' => false,
            ];
        }

        $chunks = $entries->chunk(self::CHUNK_SIZE)->values();
        $sitemapRefs = [];

        foreach ($chunks as $index => $chunk) {
            $chunkNumber = $index + 1;
            $path = self::STORAGE_DIR.'/sitemap-'.$chunkNumber.'.xml';
            $disk->put($path, $this->buildUrlSet($chunk));
            $files[] = $path;
            $sitemapRefs[] = [
                'loc' => $siteUrl.'/sitemap-'.$chunkNumber.'.xml',
                'lastmod' => now()->toAtomString(),
            ];
        }

        $indexXml = $this->buildSitemapIndex(collect($sitemapRefs));
        $disk->put(self::STORAGE_DIR.'/sitemap-index.xml', $indexXml);
        $disk->put(self::STORAGE_DIR.'/sitemap.xml', $indexXml);
        $files[] = self::STORAGE_DIR.'/sitemap-index.xml';
        $files[] = self::STORAGE_DIR.'/sitemap.xml';

        return [
            'url_count' => $entries->count(),
            'files' => $files,
            'chunked' => true,
        ];
    }

    public function readFile(string $filename): ?string
    {
        $path = self::STORAGE_DIR.'/'.$filename;

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->get($path);
    }

    public function priorityForType(EditorialContentType $type): string
    {
        return match ($type) {
            EditorialContentType::Article => '0.8',
            EditorialContentType::Story => '0.7',
            EditorialContentType::Interview => '0.75',
            EditorialContentType::Event => '0.6',
        };
    }

    private function sitemapEligibilitySql(): string
    {
        return <<<'SQL'
CASE
    WHEN EXISTS (
        SELECT 1 FROM editorial_index_rules r
        WHERE r.rubric_slug = editorial_contents.rubric_slug
          AND r.is_active = 1
    ) THEN (
        SELECT r.include_in_sitemap FROM editorial_index_rules r
        WHERE r.rubric_slug = editorial_contents.rubric_slug
          AND r.is_active = 1
        LIMIT 1
    )
    WHEN EXISTS (
        SELECT 1 FROM editorial_index_rules g
        WHERE g.rubric_slug IS NULL
          AND g.is_active = 1
    ) THEN (
        SELECT g.include_in_sitemap FROM editorial_index_rules g
        WHERE g.rubric_slug IS NULL
          AND g.is_active = 1
        LIMIT 1
    )
    ELSE 1
END = 1
SQL;
    }

    /**
     * @return array{loc: string, lastmod: string, changefreq: string, priority: string}
     */
    private function entryForContent(EditorialContent $content, string $siteUrl): array
    {
        return [
            'loc' => $this->jsonLdBuilder->canonicalUrl($content),
            'lastmod' => ($content->updated_at ?? $content->published_at ?? now())->toAtomString(),
            'changefreq' => 'weekly',
            'priority' => $this->priorityForType($content->content_type),
        ];
    }

    /**
     * @param  Collection<int, array{loc: string, lastmod: string, changefreq: string, priority: string}>  $entries
     */
    private function buildUrlSet(Collection $entries): string
    {
        $urls = $entries->map(fn (array $entry) => sprintf(
            "  <url>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n    <changefreq>%s</changefreq>\n    <priority>%s</priority>\n  </url>",
            $this->escapeXml($entry['loc']),
            $this->escapeXml($entry['lastmod']),
            $this->escapeXml($entry['changefreq']),
            $this->escapeXml($entry['priority']),
        ))->implode("\n");

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$urls}
</urlset>
XML;
    }

    /**
     * @param  Collection<int, array{loc: string, lastmod: string}>  $sitemaps
     */
    private function buildSitemapIndex(Collection $sitemaps): string
    {
        $entries = $sitemaps->map(fn (array $entry) => sprintf(
            "  <sitemap>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n  </sitemap>",
            $this->escapeXml($entry['loc']),
            $this->escapeXml($entry['lastmod']),
        ))->implode("\n");

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$entries}
</sitemapindex>
XML;
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
