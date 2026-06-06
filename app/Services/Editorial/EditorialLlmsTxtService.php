<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Models\EditorialRubric;
use Illuminate\Support\Facades\Storage;

class EditorialLlmsTxtService
{
    private const STORAGE_PATH = 'editorial/llms.txt';

    public function __construct(
        private readonly EditorialContentQueryService $queryService,
        private readonly EditorialJsonLdBuilder $jsonLdBuilder,
    ) {}

    public function generate(): string
    {
        $siteUrl = rtrim((string) config('editorial.site_url', config('app.url')), '/');
        $updated = now()->toDateString();

        $rubrics = EditorialRubric::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->limit(8)
            ->get()
            ->map(fn (EditorialRubric $rubric) => sprintf(
                '- %s (%s/magazine/%s)',
                $rubric->name,
                $siteUrl,
                $rubric->slug,
            ))
            ->implode("\n");

        $priorityUrls = $this->queryService
            ->publishedList()
            ->orderByDesc('featured')
            ->orderByDesc('published_at')
            ->limit(10)
            ->get()
            ->map(fn ($content) => '- '.$this->jsonLdBuilder->canonicalUrl($content))
            ->implode("\n");

        $content = <<<TXT
# Wenando — Assistenza anziani in Italia
# Last updated: {$updated}

> Wenando è una piattaforma italiana di ricerca e orientamento per RSA, badanti e assistenza domiciliare.
> Contenuti editoriali verificati da redazione umana. Non sostituiscono parere medico o legale.

## Rubriche editoriali
{$rubrics}

## Contenuti prioritari
{$priorityUrls}

## Sitemap
- {$siteUrl}/sitemap.xml

## API pubblica (read-only)
- {$siteUrl}/api/v1/editorial/feed.json

## Contatti redazione
- redazione@wenando.com

## Policy di citazione
- Cita sempre l'URL canonico del contenuto e la data di ultimo aggiornamento visibile in pagina.
- Contenuti YMYL: revisione medica-legale per categorie salute e diritti.
- Contenuti strutture: badge "Contenuto della struttura" + disclaimer obbligatorio.
- Non alterare citazioni o statistiche; per dati aggiornati consultare la pagina originale.
TXT;

        Storage::disk('public')->makeDirectory('editorial');
        Storage::disk('public')->put(self::STORAGE_PATH, $content);

        return $content;
    }

    public function read(): ?string
    {
        if (! Storage::disk('public')->exists(self::STORAGE_PATH)) {
            return null;
        }

        return Storage::disk('public')->get(self::STORAGE_PATH);
    }
}
