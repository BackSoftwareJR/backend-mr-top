<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\EditorialContent;
use App\Models\EditorialIndexQueue;
use App\Models\EditorialIndexRule;
use App\Services\Editorial\EditorialIndexQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditorialIndexController extends Controller
{
    public function __construct(
        private readonly EditorialIndexQueueService $indexQueueService,
    ) {}

    public function indexRules(): JsonResponse
    {
        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('manageIndex', EditorialContent::class);

        $rules = EditorialIndexRule::query()
            ->orderByRaw('rubric_slug IS NULL DESC')
            ->orderBy('rubric_slug')
            ->get()
            ->map(fn (EditorialIndexRule $rule): array => $this->rulePayload($rule))
            ->all();

        return ApiEnvelope::success(['rules' => $rules]);
    }

    public function updateIndexRule(Request $request, int $id): JsonResponse
    {
        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('manageIndex', EditorialContent::class);

        $validated = $request->validate([
            'include_in_sitemap' => ['sometimes', 'boolean'],
            'include_in_internal_search' => ['sometimes', 'boolean'],
            'noindex_default' => ['sometimes', 'boolean'],
            'exclude_from_crawl' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        /** @var EditorialIndexRule $rule */
        $rule = EditorialIndexRule::query()->findOrFail($id);
        $rule->update($validated);

        return ApiEnvelope::success(['rule' => $this->rulePayload($rule->fresh())]);
    }

    public function reindex(Request $request): JsonResponse
    {
        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('manageIndex', EditorialContent::class);

        $validated = $request->validate([
            'content_uuid' => ['sometimes', 'nullable', 'uuid', 'exists:editorial_contents,uuid'],
            'rubric_slug' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        if (! empty($validated['content_uuid'])) {
            $content = EditorialContent::query()
                ->where('uuid', $validated['content_uuid'])
                ->firstOrFail();

            $entries = $this->indexQueueService->enqueueReindexForContent($content);
        } elseif (! empty($validated['rubric_slug'])) {
            $entries = $this->indexQueueService->enqueueReindexForRubric($validated['rubric_slug']);
        } else {
            $entries = $this->indexQueueService->enqueueReindexAllPublished();
        }

        return ApiEnvelope::success([
            'queued' => $entries->count(),
            'queue_ids' => $entries->pluck('id')->all(),
        ]);
    }

    public function indexQueue(Request $request): JsonResponse
    {
        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('manageIndex', EditorialContent::class);

        $limit = min(max($request->integer('limit', 25), 1), 100);

        $entries = EditorialIndexQueue::query()
            ->with(['editorialContent:id,uuid,title,slug,status'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (EditorialIndexQueue $entry): array => [
                'id' => $entry->id,
                'action' => $entry->action?->value,
                'status' => $entry->status?->value,
                'scheduled_at' => $entry->scheduled_at?->toIso8601String(),
                'processed_at' => $entry->processed_at?->toIso8601String(),
                'error_message' => $entry->error_message,
                'content' => $entry->editorialContent === null ? null : [
                    'uuid' => $entry->editorialContent->uuid,
                    'title' => $entry->editorialContent->title,
                    'slug' => $entry->editorialContent->slug,
                    'status' => $entry->editorialContent->status?->value,
                ],
            ])
            ->all();

        return ApiEnvelope::success(['items' => $entries]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rulePayload(EditorialIndexRule $rule): array
    {
        return [
            'id' => $rule->id,
            'rubric_slug' => $rule->rubric_slug,
            'scope' => $rule->rubric_slug === null ? 'global' : 'rubric',
            'include_in_sitemap' => $rule->include_in_sitemap,
            'include_in_internal_search' => $rule->include_in_internal_search,
            'noindex_default' => $rule->noindex_default,
            'exclude_from_crawl' => $rule->exclude_from_crawl,
            'is_active' => $rule->is_active,
            'notes' => $rule->notes,
            'updated_at' => $rule->updated_at?->toIso8601String(),
        ];
    }
}
