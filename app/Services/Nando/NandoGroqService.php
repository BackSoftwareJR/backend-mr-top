<?php

declare(strict_types=1);

namespace App\Services\Nando;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NandoGroqService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Sei Nando, la guida di ricerca di Wenando per l'assistenza agli anziani in Italia.
Rispondi sempre in italiano. Tratta solo argomenti di assistenza agli anziani (RSA, badanti, assistenza domiciliare, case di riposo, centri diurni, ecc.).
Se la richiesta è fuori tema, imposta supported=false, question=null, complete=true e un pageTitle breve che spiega il limite.
Non inventare mai nomi di strutture, contatti o indirizzi reali.
Restituisci SOLO un oggetto JSON valido con questa struttura:
{
  "pageTitle": "string — titolo editoriale conciso per la pagina risultati",
  "supported": true,
  "question": {
    "id": "refinement_xxx",
    "question": "...",
    "hint": "...",
    "options": [{"id": "...", "label": "..."}, ... esattamente 3]
  } | null,
  "complete": false
}
Quando hai raccolto abbastanza informazioni, imposta question=null e complete=true.
PROMPT;

    public function isConfigured(): bool
    {
        $apiKey = config('services.groq.api_key');

        return is_string($apiKey) && $apiKey !== '';
    }

    /**
     * @param  array<string, mixed>  $selections
     * @param  list<array<string, mixed>>  $refinementHistory
     * @return ?array{pageTitle: string, supported: bool, question: ?array<string, mixed>, complete: bool}
     */
    public function refine(
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
                ->timeout(15)
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
                Log::warning('nando.groq.http_error', [
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
            Log::warning('nando.groq.failed', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return ?array{pageTitle: string, supported: bool, question: ?array<string, mixed>, complete: bool}
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
        $complete = (bool) ($decoded['complete'] ?? false);
        $question = $decoded['question'] ?? null;

        if ($complete) {
            $question = null;
        } elseif ($supported) {
            $question = $this->validateQuestion($question);
            if ($question === null) {
                return null;
            }
        } else {
            $question = null;
            $complete = true;
        }

        return [
            'pageTitle' => $pageTitle,
            'supported' => $supported,
            'question' => $question,
            'complete' => $complete,
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
