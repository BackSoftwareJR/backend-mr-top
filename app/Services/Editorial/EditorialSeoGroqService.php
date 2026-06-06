<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialSeoGenerationStatus;
use App\Models\EditorialContent;
use App\Models\EditorialSeoGeneration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EditorialSeoGroqService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Sei il responsabile SEO editoriale di Wenando, piattaforma YMYL per assistenza anziani in Italia.
Input: titolo, sottotitolo, body_blocks (JSON), content_type, rubric.
Output: SOLO un oggetto JSON valido con questa struttura:
{
  "seo_title": "string 50-60 caratteri ideali",
  "seo_description": "string 150-160 caratteri ideali",
  "excerpt": "string teaser 120-300 caratteri",
  "og_title": "string",
  "og_description": "string",
  "primary_keyword": "string",
  "secondary_keywords": ["string", ...],
  "suggested_tags": ["string", ...],
  "json_ld_hints": { "schema_type": "Article|FAQPage|Event", "faq_items": [{"question":"...","answer":"..."}] | null }
}

Regole:
- Italiano corretto, tono empatico e autorevole, mai sensazionalistico
- YMYL: nessuna promessa medica, nessun claim non verificabile
- seo_title unico, descrittivo, keyword primaria naturale
- seo_description accurata, invito all'azione soft
- Se ci sono blocchi FAQ, popola json_ld_hints.faq_items
PROMPT;

    public function __construct(
        private readonly EditorialSeoScorer $scorer,
    ) {}

    public function isConfigured(): bool
    {
        $apiKey = config('services.groq.api_key');

        return is_string($apiKey) && $apiKey !== '';
    }

    public function generateAndStore(EditorialContent $content): EditorialSeoGeneration
    {
        $content->loadMissing('rubric');

        $start = microtime(true);
        $groqPayload = $this->buildInputPayload($content);
        $inputHash = hash('sha256', json_encode($groqPayload, JSON_THROW_ON_ERROR));

        $rawPack = $this->isConfigured()
            ? $this->callGroq($content, $groqPayload)
            : null;

        $source = 'groq';

        if ($rawPack === null) {
            $rawPack = $this->buildFallbackSeoPack($content);
            $source = 'fallback';
        }

        $scored = $this->scorer->score($content, $rawPack);
        $seoPack = $this->finalizeSeoPack($rawPack, $scored, $source);

        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        return EditorialSeoGeneration::query()->create([
            'content_id' => $content->id,
            'groq_payload' => $groqPayload,
            'seo_pack' => $seoPack,
            'score' => $scored['score'],
            'status' => EditorialSeoGenerationStatus::Pending,
            'groq_model' => $this->isConfigured() ? (string) config('editorial.seo.groq_model') : null,
            'prompt_version' => (string) config('editorial.seo.prompt_version'),
            'latency_ms' => $latencyMs,
            'error_message' => $source === 'fallback' && $this->isConfigured() ? 'Groq call failed; used rule-based fallback' : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInputPayload(EditorialContent $content): array
    {
        return [
            'title' => $content->title,
            'subtitle' => $content->subtitle,
            'content_type' => $content->content_type?->value,
            'rubric' => $content->rubric?->name ?? $content->rubric_slug,
            'body_blocks' => $content->body_blocks ?? [],
            'input_hash' => hash('sha256', implode('|', [
                $content->title,
                $content->subtitle ?? '',
                json_encode($content->body_blocks ?? [], JSON_THROW_ON_ERROR),
            ])),
        ];
    }

    /**
     * @param  array<string, mixed>  $groqPayload
     * @return ?array<string, mixed>
     */
    private function callGroq(EditorialContent $content, array $groqPayload): ?array
    {
        $baseUrl = rtrim((string) config('services.groq.base_url'), '/');
        $model = (string) config('editorial.seo.groq_model');
        $apiKey = (string) config('services.groq.api_key');

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(30)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        [
                            'role' => 'user',
                            'content' => json_encode($groqPayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.3,
                ]);

            if (! $response->successful()) {
                Log::warning('editorial.seo.groq.http_error', [
                    'content_id' => $content->id,
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                ]);

                return null;
            }

            $contentRaw = $response->json('choices.0.message.content');

            if (! is_string($contentRaw) || trim($contentRaw) === '') {
                return null;
            }

            /** @var mixed $decoded */
            $decoded = json_decode($contentRaw, true, 512, JSON_THROW_ON_ERROR);

            return $this->validateGroqSeoPack($decoded, $content);
        } catch (\Throwable $exception) {
            Log::warning('editorial.seo.groq.failed', [
                'content_id' => $content->id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFallbackSeoPack(EditorialContent $content): array
    {
        $title = trim($content->title);
        $excerpt = trim((string) ($content->excerpt ?? ''));
        $bodyText = $this->extractPlainText($content->body_blocks ?? []);

        if ($excerpt === '') {
            $excerpt = Str::limit($bodyText, 250, '…');
        }

        $seoTitle = Str::limit($title, 58, '…');
        $seoDescription = Str::limit(
            $excerpt !== '' ? $excerpt : $bodyText,
            158,
            '…',
        );

        $primaryKeyword = $this->guessPrimaryKeyword($title, $content->rubric_slug);
        $blocks = $content->body_blocks ?? [];
        $hasFaq = $this->scorer->hasFaqBlocks($blocks);

        return [
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'excerpt' => $excerpt,
            'og_title' => $seoTitle,
            'og_description' => $seoDescription,
            'primary_keyword' => $primaryKeyword,
            'secondary_keywords' => array_values(array_filter([
                $primaryKeyword,
                $content->rubric_slug,
            ])),
            'suggested_tags' => $content->tags ?? [],
            'json_ld_hints' => [
                'schema_type' => $hasFaq ? 'FAQPage' : 'Article',
                'faq_items' => $hasFaq ? $this->scorer->extractFaqHints($blocks) : null,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function extractPlainText(array $blocks): string
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

            $extracted = match ($type) {
                'heading' => [strip_tags((string) ($data['text'] ?? ''))],
                'paragraph' => [strip_tags((string) ($data['html'] ?? $data['text'] ?? ''))],
                'callout' => [strip_tags((string) ($data['body'] ?? $data['text'] ?? ''))],
                'quote' => [strip_tags((string) ($data['text'] ?? ''))],
                'layout' => $this->extractLayoutPlainText($data),
                default => [],
            };

            foreach ($extracted as $part) {
                $part = trim($part);

                if ($part !== '') {
                    $parts[] = $part;
                }
            }
        }

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function extractLayoutPlainText(array $data): array
    {
        $templateId = (string) ($data['template_id'] ?? '');
        $slots = is_array($data['slots'] ?? null) ? $data['slots'] : [];
        $textParts = app(EditorialLayoutRenderer::class)->extractPlainTextFromSlots($slots);

        if ($templateId !== '') {
            array_unshift($textParts, 'layout:'.$templateId);
        }

        return $textParts;
    }

    private function guessPrimaryKeyword(string $title, ?string $rubricSlug): string
    {
        $words = preg_split('/\s+/', mb_strtolower($title)) ?: [];
        $stopWords = ['di', 'da', 'per', 'il', 'la', 'le', 'un', 'una', 'e', 'in', 'a', 'che', 'come'];

        $filtered = array_values(array_filter($words, static fn (string $w): bool => ! in_array($w, $stopWords, true) && mb_strlen($w) > 2));

        if ($filtered !== []) {
            return Str::limit(implode(' ', array_slice($filtered, 0, 3)), 80, '');
        }

        return (string) ($rubricSlug ?? 'assistenza anziani');
    }

    /**
     * @return ?array<string, mixed>
     */
    private function validateGroqSeoPack(mixed $decoded, EditorialContent $content): ?array
    {
        if (! is_array($decoded)) {
            return null;
        }

        $seoTitle = trim(strip_tags((string) ($decoded['seo_title'] ?? '')));
        $seoDescription = trim(strip_tags((string) ($decoded['seo_description'] ?? '')));

        if ($seoTitle === '' || $seoDescription === '') {
            return null;
        }

        $blocks = $content->body_blocks ?? [];
        $hasFaq = $this->scorer->hasFaqBlocks($blocks);
        $jsonLdHints = $decoded['json_ld_hints'] ?? [];
        $jsonLdHints = is_array($jsonLdHints) ? $jsonLdHints : [];

        if ($hasFaq && empty($jsonLdHints['faq_items'])) {
            $jsonLdHints['faq_items'] = $this->scorer->extractFaqHints($blocks);
            $jsonLdHints['schema_type'] = 'FAQPage';
        }

        return [
            'seo_title' => Str::limit($seoTitle, 70, ''),
            'seo_description' => Str::limit($seoDescription, 320, ''),
            'excerpt' => Str::limit(trim(strip_tags((string) ($decoded['excerpt'] ?? $seoDescription))), 500, ''),
            'og_title' => Str::limit(trim(strip_tags((string) ($decoded['og_title'] ?? $seoTitle))), 70, ''),
            'og_description' => Str::limit(trim(strip_tags((string) ($decoded['og_description'] ?? $seoDescription))), 200, ''),
            'primary_keyword' => Str::limit(trim(strip_tags((string) ($decoded['primary_keyword'] ?? ''))), 80, ''),
            'secondary_keywords' => $this->sanitizeStringList($decoded['secondary_keywords'] ?? [], 10),
            'suggested_tags' => $this->sanitizeStringList($decoded['suggested_tags'] ?? [], 12),
            'json_ld_hints' => [
                'schema_type' => (string) ($jsonLdHints['schema_type'] ?? ($hasFaq ? 'FAQPage' : 'Article')),
                'faq_items' => $jsonLdHints['faq_items'] ?? null,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function sanitizeStringList(mixed $values, int $max): array
    {
        if (! is_array($values)) {
            return [];
        }

        $result = [];

        foreach ($values as $value) {
            $text = trim(strip_tags((string) $value));

            if ($text !== '') {
                $result[] = Str::limit($text, 80, '');
            }

            if (count($result) >= $max) {
                break;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $rawPack
     * @param  array{score: int, breakdown: array<string, int>}  $scored
     * @return array<string, mixed>
     */
    private function finalizeSeoPack(array $rawPack, array $scored, string $source): array
    {
        return array_merge($rawPack, [
            'version' => 3,
            'generated_at' => now()->toIso8601String(),
            'generated_by' => $source,
            'approved' => false,
            'approved_by_user_id' => null,
            'approved_at' => null,
            'seo_score' => $scored['score'],
            'seo_score_breakdown' => $scored['breakdown'],
        ]);
    }
}
