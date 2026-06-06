<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2C;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Services\Nando\NandoEditorialContextService;
use App\Services\Nando\NandoGroqService;
use App\Services\Nando\NandoRefineFallback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NandoController extends Controller
{
    public function __construct(
        private readonly NandoGroqService $groq,
        private readonly NandoEditorialContextService $editorialContext,
    ) {}

    public function refine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:500'],
            'selections' => ['sometimes', 'array'],
            'customNotes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'refinementHistory' => ['sometimes', 'array'],
        ]);

        $query = trim($validated['query']);
        $selections = $validated['selections'] ?? [];
        $customNotes = $validated['customNotes'] ?? null;
        $refinementHistory = $validated['refinementHistory'] ?? [];

        $snippets = $this->editorialContext->topSnippets($query, 5);

        if ($this->groq->isConfigured()) {
            $groqResult = $this->groq->refine($query, $selections, $customNotes, $refinementHistory, $snippets);

            if ($groqResult !== null) {
                return ApiEnvelope::success($groqResult, meta: ['source' => 'groq']);
            }
        }

        $fallback = NandoRefineFallback::refine($query, $selections, $customNotes, $refinementHistory);

        return ApiEnvelope::success($fallback, meta: ['source' => 'fallback']);
    }

    public function editorialContext(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:500'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $snippets = $this->editorialContext->topSnippets(
            trim($validated['q']),
            (int) ($validated['limit'] ?? 5),
        );

        return ApiEnvelope::success(['snippets' => $snippets]);
    }
}
