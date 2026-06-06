<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2C;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\B2C\ContactIntentRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\LeadResource;
use App\Services\ExploreContactIntentService;
use App\Services\Search\EditorialSearchService;
use App\Services\Search\SearchOrchestratorFallback;
use App\Services\Search\SearchOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends Controller
{
    public function __construct(
        private readonly SearchOrchestratorService $orchestrator,
        private readonly ExploreContactIntentService $contactIntentService,
        private readonly EditorialSearchService $editorialSearch,
    ) {}

    public function orchestrate(Request $request): JsonResponse
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

        if ($this->orchestrator->isConfigured()) {
            $groqResult = $this->orchestrator->orchestrate($query, $selections, $customNotes, $refinementHistory);

            if ($groqResult !== null) {
                return ApiEnvelope::success($groqResult, meta: ['source' => 'groq']);
            }
        }

        $fallback = SearchOrchestratorFallback::orchestrate($query, $selections, $customNotes, $refinementHistory);

        return ApiEnvelope::success($fallback, meta: ['source' => 'fallback']);
    }

    public function editorial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sector' => ['sometimes', 'nullable', 'string', 'max:80'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $items = $this->editorialSearch->search(
            $validated['q'] ?? null,
            $validated['sector'] ?? null,
            (int) ($validated['limit'] ?? 5),
        );

        return ApiEnvelope::success(['items' => $items]);
    }

    public function contactIntent(ContactIntentRequest $request): JsonResponse
    {
        $result = $this->contactIntentService->submit($request, Auth::user());

        return ApiEnvelope::success(
            [
                'lead' => new LeadResource($result['lead']),
                'matches' => $result['matches'],
                'match_count' => count($result['matches']),
            ],
            Response::HTTP_CREATED,
        );
    }
}
