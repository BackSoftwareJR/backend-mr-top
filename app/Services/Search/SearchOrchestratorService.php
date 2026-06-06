<?php

declare(strict_types=1);

namespace App\Services\Search;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SearchOrchestratorService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Sei Nando, la guida di ricerca di Wenando per l'assistenza agli anziani in Italia.
Rispondi sempre in italiano. Tratta solo argomenti di assistenza agli anziani (RSA, badanti, assistenza domiciliare, case di riposo, centri diurni, ecc.).

IMPORTANTE — PERCORSI, NON STRUTTURE:
Proponi esattamente 3 PERCORSI DI ESPLORAZIONE (tipologie di soluzione), NON nomi di strutture specifiche.
Esempi di percorsi validi: "Assistenza domiciliare", "RSA con assistenza media", "Centro diurno", "Casa famiglia".
NON inventare mai nomi di strutture reali, contatti, indirizzi, telefoni o siti web di RSA/agenzie.

Se la richiesta è fuori tema, imposta supported=false, paths=[], editorial=[], nando.question=null.

Restituisci SOLO un oggetto JSON valido con questa struttura:
{
  "pageTitle": "string — titolo editoriale conciso per la pagina risultati",
  "supported": true,
  "paths": [
    {
      "id": "path_rsa",
      "type": "structure|service|editorial",
      "rank": 1,
      "label": "Scelta consigliata|Alternativa valida|Da valutare",
      "title": "nome del percorso (tipologia, non struttura)",
      "subtitle": "sottotitolo breve, es. Percorso · Casa",
      "summary": "descrizione orientativa 1-2 frasi",
      "whyRecommended": "motivazione personalizzata basata su query e selezioni",
      "refinementNeeded": ["zone", "autonomy", "budget"],
      "imageHint": "rsa|home_care|community"
    }
  ],
  "editorial": [
    {
      "id": "editorial-xxx",
      "title": "titolo articolo generico",
      "summary": "breve descrizione",
      "url": "#",
      "relevanceReason": "perché è rilevante per questa ricerca"
    }
  ],
  "nando": {
    "microPrompt": "frase breve per invitare ad affinare",
    "actions": [{"id": "explain_why", "label": "Scopri perché ti abbiamo consigliato"}],
    "question": {
      "id": "refinement_xxx",
      "question": "...",
      "hint": "...",
      "options": [{"id": "...", "label": "..."}, ... esattamente 3]
    } | null
  }
}

Regole:
- paths deve contenere esattamente 3 elementi con rank 1, 2, 3.
- type "structure" = percorsi verso strutture (RSA, casa famiglia); "service" = servizi (domiciliare, centro diurno); "editorial" = approfondimento guidato.
- refinementNeeded: elenca i campi ancora utili (zone, autonomy, budget) in base a cosa manca nelle selections.
- editorial: 2-4 voci generiche, url sempre "#" se non hai un link reale.
- nando.question: la prossima domanda di affinamento, o null se non serve altro.
PROMPT;

    /** @var list<string> */
    private const ALLOWED_PATH_TYPES = ['structure', 'service', 'editorial'];

    /** @var list<string> */
    private const ALLOWED_IMAGE_HINTS = ['rsa', 'home_care', 'community'];

    /** @var list<string> */
    private const ALLOWED_REFINEMENT_KEYS = ['zone', 'autonomy', 'budget'];

    public function isConfigured(): bool
    {
        $apiKey = config('services.groq.api_key');

        return is_string($apiKey) && $apiKey !== '';
    }

    /**
     * @param  array<string, mixed>  $selections
     * @param  list<array<string, mixed>>  $refinementHistory
     * @return ?array{
     *   pageTitle: string,
     *   supported: bool,
     *   paths: list<array<string, mixed>>,
     *   editorial: list<array<string, mixed>>,
     *   nando: array<string, mixed>
     * }
     */
    public function orchestrate(
        string $query,
        array $selections,
        ?string $customNotes,
        array $refinementHistory,
    ): ?array {
        if (! $this->isConfigured()) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.groq.base_url'), '/');
        $model = (string) config('services.groq.model');
        $apiKey = (string) config('services.groq.api_key');

        $userPayload = [
            'query' => $query,
            'selections' => $selections,
            'customNotes' => $customNotes ?? '',
            'refinementHistory' => $refinementHistory,
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(20)
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        [
                            'role' => 'user',
                            'content' => json_encode($userPayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.3,
                ]);

            if (! $response->successful()) {
                Log::warning('search.orchestrator.groq.http_error', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                ]);

                return null;
            }

            $content = $response->json('choices.0.message.content');
            if (! is_string($content) || trim($content) === '') {
                return null;
            }

            /** @var mixed $decoded */
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return $this->validateResponse($decoded);
        } catch (\Throwable $exception) {
            Log::warning('search.orchestrator.groq.failed', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return ?array{
     *   pageTitle: string,
     *   supported: bool,
     *   paths: list<array<string, mixed>>,
     *   editorial: list<array<string, mixed>>,
     *   nando: array<string, mixed>
     * }
     */
    private function validateResponse(mixed $decoded): ?array
    {
        if (! is_array($decoded)) {
            return null;
        }

        $pageTitle = isset($decoded['pageTitle']) ? trim(strip_tags((string) $decoded['pageTitle'])) : '';
        if ($pageTitle === '') {
            return null;
        }

        $pageTitle = Str::limit($pageTitle, 120, '');
        $supported = (bool) ($decoded['supported'] ?? true);

        if (! $supported) {
            return [
                'pageTitle' => $pageTitle,
                'supported' => false,
                'paths' => [],
                'editorial' => $this->validateEditorial($decoded['editorial'] ?? []),
                'nando' => [
                    'microPrompt' => 'Argomento non supportato',
                    'actions' => [],
                    'question' => null,
                ],
            ];
        }

        $paths = $this->validatePaths($decoded['paths'] ?? null);
        if ($paths === null) {
            return null;
        }

        $editorial = $this->validateEditorial($decoded['editorial'] ?? []);
        $nando = $this->validateNando($decoded['nando'] ?? null);

        if ($nando === null) {
            return null;
        }

        return [
            'pageTitle' => $pageTitle,
            'supported' => true,
            'paths' => $paths,
            'editorial' => $editorial,
            'nando' => $nando,
        ];
    }

    /**
     * @return ?list<array<string, mixed>>
     */
    private function validatePaths(mixed $paths): ?array
    {
        if (! is_array($paths) || count($paths) !== 3) {
            return null;
        }

        $sanitized = [];
        foreach ($paths as $path) {
            if (! is_array($path)) {
                return null;
            }

            $validated = $this->validatePath($path);
            if ($validated === null) {
                return null;
            }

            $sanitized[] = $validated;
        }

        usort($sanitized, fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);

        return $sanitized;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function validatePath(array $path): ?array
    {
        $id = isset($path['id']) ? trim((string) $path['id']) : '';
        $type = isset($path['type']) ? trim((string) $path['type']) : '';
        $rank = isset($path['rank']) ? (int) $path['rank'] : 0;
        $label = isset($path['label']) ? trim(strip_tags((string) $path['label'])) : '';
        $title = isset($path['title']) ? trim(strip_tags((string) $path['title'])) : '';
        $subtitle = isset($path['subtitle']) ? trim(strip_tags((string) $path['subtitle'])) : '';
        $summary = isset($path['summary']) ? trim(strip_tags((string) $path['summary'])) : '';
        $whyRecommended = isset($path['whyRecommended']) ? trim(strip_tags((string) $path['whyRecommended'])) : '';
        $imageHint = isset($path['imageHint']) ? trim((string) $path['imageHint']) : '';

        if ($id === '' || $title === '' || $summary === '' || $whyRecommended === '') {
            return null;
        }

        if (! in_array($type, self::ALLOWED_PATH_TYPES, true)) {
            return null;
        }

        if ($rank < 1 || $rank > 3) {
            return null;
        }

        if (! in_array($imageHint, self::ALLOWED_IMAGE_HINTS, true)) {
            $imageHint = 'home_care';
        }

        $refinementNeeded = [];
        if (isset($path['refinementNeeded']) && is_array($path['refinementNeeded'])) {
            foreach ($path['refinementNeeded'] as $key) {
                $normalized = trim((string) $key);
                if (in_array($normalized, self::ALLOWED_REFINEMENT_KEYS, true)) {
                    $refinementNeeded[] = $normalized;
                }
            }
        }

        return [
            'id' => Str::limit($id, 64, ''),
            'type' => $type,
            'rank' => $rank,
            'label' => Str::limit($label !== '' ? $label : 'Percorso consigliato', 80, ''),
            'title' => Str::limit($title, 120, ''),
            'subtitle' => Str::limit($subtitle, 120, ''),
            'summary' => Str::limit($summary, 400, ''),
            'whyRecommended' => Str::limit($whyRecommended, 500, ''),
            'refinementNeeded' => $refinementNeeded,
            'imageHint' => $imageHint,
        ];
    }

    /**
     * @return list<array{id: string, title: string, summary: string, url: string, relevanceReason: string}>
     */
    private function validateEditorial(mixed $editorial): array
    {
        if (! is_array($editorial)) {
            return [];
        }

        $sanitized = [];
        foreach ($editorial as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = isset($item['id']) ? trim((string) $item['id']) : '';
            $title = isset($item['title']) ? trim(strip_tags((string) $item['title'])) : '';
            $summary = isset($item['summary']) ? trim(strip_tags((string) $item['summary'])) : '';
            $url = isset($item['url']) ? trim((string) $item['url']) : '#';
            $relevanceReason = isset($item['relevanceReason']) ? trim(strip_tags((string) $item['relevanceReason'])) : '';

            if ($id === '' || $title === '' || $summary === '') {
                continue;
            }

            if ($url === '' || ! str_starts_with($url, '#') && ! filter_var($url, FILTER_VALIDATE_URL)) {
                $url = '#';
            }

            $sanitized[] = [
                'id' => Str::limit($id, 64, ''),
                'title' => Str::limit($title, 200, ''),
                'summary' => Str::limit($summary, 400, ''),
                'url' => Str::limit($url, 500, ''),
                'relevanceReason' => Str::limit($relevanceReason, 300, ''),
            ];
        }

        return $sanitized;
    }

    /**
     * @return ?array{microPrompt: string, actions: list<array{id: string, label: string}>, question: ?array<string, mixed>}
     */
    private function validateNando(mixed $nando): ?array
    {
        if (! is_array($nando)) {
            return null;
        }

        $microPrompt = isset($nando['microPrompt']) ? trim(strip_tags((string) $nando['microPrompt'])) : '';
        if ($microPrompt === '') {
            $microPrompt = 'Affina la ricerca con Nando';
        }

        $actions = [];
        if (isset($nando['actions']) && is_array($nando['actions'])) {
            foreach ($nando['actions'] as $action) {
                if (! is_array($action)) {
                    continue;
                }

                $actionId = isset($action['id']) ? trim((string) $action['id']) : '';
                $label = isset($action['label']) ? trim(strip_tags((string) $action['label'])) : '';

                if ($actionId === '' || $label === '') {
                    continue;
                }

                $actions[] = [
                    'id' => Str::limit($actionId, 64, ''),
                    'label' => Str::limit($label, 120, ''),
                ];
            }
        }

        if ($actions === []) {
            $actions[] = [
                'id' => 'explain_why',
                'label' => 'Scopri perché ti abbiamo consigliato',
            ];
        }

        $question = $nando['question'] ?? null;
        $validatedQuestion = $question === null ? null : $this->validateQuestion($question);

        if ($question !== null && $validatedQuestion === null) {
            return null;
        }

        return [
            'microPrompt' => Str::limit($microPrompt, 200, ''),
            'actions' => $actions,
            'question' => $validatedQuestion,
        ];
    }

    /**
     * @return ?array{id: string, question: string, hint: string, options: list<array{id: string, label: string}>}
     */
    private function validateQuestion(mixed $question): ?array
    {
        if (! is_array($question)) {
            return null;
        }

        $id = isset($question['id']) ? trim((string) $question['id']) : '';
        $text = isset($question['question']) ? trim(strip_tags((string) $question['question'])) : '';
        $hint = isset($question['hint']) ? trim(strip_tags((string) $question['hint'])) : '';
        $options = $question['options'] ?? null;

        if ($id === '' || $text === '' || ! is_array($options) || count($options) !== 3) {
            return null;
        }

        $sanitizedOptions = [];
        foreach ($options as $option) {
            if (! is_array($option)) {
                return null;
            }

            $optionId = isset($option['id']) ? trim((string) $option['id']) : '';
            $label = isset($option['label']) ? trim(strip_tags((string) $option['label'])) : '';

            if ($optionId === '' || $label === '') {
                return null;
            }

            $sanitizedOptions[] = [
                'id' => Str::limit($optionId, 64, ''),
                'label' => Str::limit($label, 120, ''),
            ];
        }

        return [
            'id' => Str::limit($id, 64, ''),
            'question' => Str::limit($text, 300, ''),
            'hint' => Str::limit($hint, 200, ''),
            'options' => $sanitizedOptions,
        ];
    }
}
