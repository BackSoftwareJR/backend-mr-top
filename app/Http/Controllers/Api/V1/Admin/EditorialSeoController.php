<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\ApproveEditorialSeoRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\EditorialContentResource;
use App\Models\EditorialContent;
use App\Models\EditorialSeoGeneration;
use App\Services\Editorial\EditorialSeoGroqService;
use App\Services\Editorial\EditorialSeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditorialSeoController extends Controller
{
    public function __construct(
        private readonly EditorialSeoService $seoService,
        private readonly EditorialSeoGroqService $seoGroqService,
    ) {}

    public function show(string $uuid): JsonResponse
    {
        $content = $this->findContent($uuid);

        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('view', $content);

        $history = $this->seoService->history($content);

        return ApiEnvelope::success([
            'latest' => $history['latest'] ? $this->formatGeneration($history['latest']) : null,
            'history' => array_map(
                fn (EditorialSeoGeneration $generation): array => $this->formatGeneration($generation),
                $history['history'],
            ),
            'content_seo_pack' => $content->seo_pack,
            'groq_configured' => $this->seoGroqService->isConfigured(),
        ]);
    }

    public function regenerate(string $uuid): JsonResponse
    {
        $content = $this->findContent($uuid);

        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('regenerateSeo', $content);

        $generation = $this->seoService->regenerate($content);

        return ApiEnvelope::success([
            'generation' => $this->formatGeneration($generation),
        ], 201);
    }

    public function approve(ApproveEditorialSeoRequest $request, string $uuid): JsonResponse
    {
        $content = $this->findContent($uuid);

        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('approveSeo', $content);

        $validated = $request->validated();
        $generationId = isset($validated['generation_id']) ? (int) $validated['generation_id'] : null;
        $manualOverrides = $validated['manual_overrides'] ?? null;

        $content = $this->seoService->approve(
            $content,
            $request->user(),
            $generationId,
            is_array($manualOverrides) ? $manualOverrides : null,
        );

        return ApiEnvelope::success([
            'content' => new EditorialContentResource($content),
        ]);
    }

    public function reject(Request $request, string $uuid): JsonResponse
    {
        $content = $this->findContent($uuid);

        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('approveSeo', $content);

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
            'generation_id' => ['nullable', 'integer'],
        ]);

        $generationId = isset($validated['generation_id']) ? (int) $validated['generation_id'] : null;

        $generation = $this->seoService->reject(
            $content,
            $request->user(),
            $validated['note'] ?? null,
            $generationId,
        );

        return ApiEnvelope::success([
            'generation' => $this->formatGeneration($generation),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatGeneration(EditorialSeoGeneration $generation): array
    {
        return [
            'id' => $generation->id,
            'content_id' => $generation->content_id,
            'seo_pack' => $generation->seo_pack,
            'score' => $generation->score,
            'status' => $generation->status?->value,
            'groq_model' => $generation->groq_model,
            'prompt_version' => $generation->prompt_version,
            'latency_ms' => $generation->latency_ms,
            'error_message' => $generation->error_message,
            'reviewed_by_user_id' => $generation->reviewed_by_user_id,
            'reviewed_at' => $generation->reviewed_at?->toIso8601String(),
            'created_at' => $generation->created_at?->toIso8601String(),
        ];
    }

    private function findContent(string $uuid): EditorialContent
    {
        return EditorialContent::query()->where('uuid', $uuid)->firstOrFail();
    }
}
