<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\TransitionEditorialContentRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\EditorialContentResource;
use App\Models\EditorialContent;
use App\Services\Editorial\EditorialOptimisticLock;
use App\Services\Editorial\EditorialWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditorialWorkflowController extends Controller
{
    public function __construct(
        private readonly EditorialWorkflowService $workflowService,
        private readonly EditorialOptimisticLock $optimisticLock,
    ) {}

    public function transition(TransitionEditorialContentRequest $request, string $uuid): JsonResponse
    {
        $content = $this->findContent($uuid);
        $toStatus = EditorialContentStatus::from($request->validated('to_status'));

        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('transition', [$content, $toStatus]);
        $this->optimisticLock->assertVersionMatches($content, $request);

        $content = $this->workflowService->transition(
            $content,
            $toStatus,
            $request->user(),
            $request->validated('note'),
        );

        return ApiEnvelope::success([
            'content' => new EditorialContentResource($content),
        ]);
    }

    public function reviewQueue(Request $request): JsonResponse
    {
        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('viewReviewQueue', EditorialContent::class);

        $query = EditorialContent::query()
            ->with(['rubric', 'moderationQueueEntry', 'company'])
            ->where('status', EditorialContentStatus::PendingReview);

        if ($request->filled('type')) {
            $query->where('content_type', $request->string('type')->toString());
        }

        if ($request->filled('content_type')) {
            $query->where('content_type', $request->string('content_type')->toString());
        }

        if ($request->filled('rubric_id')) {
            $query->where('rubric_id', $request->integer('rubric_id'));
        }

        if ($request->filled('author_type')) {
            $query->where('author_type', $request->string('author_type')->toString());
        }

        if ($request->boolean('structure_only')) {
            $query->where('author_type', EditorialAuthorType::Company->value)
                ->whereNotNull('company_id');
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where('title', 'like', $term);
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $paginator = $query->orderByDesc('updated_at')->paginate($perPage);

        $items = collect($paginator->items())
            ->map(function (EditorialContent $content): array {
                $moderation = $content->moderationQueueEntry;

                return [
                    'content' => EditorialContentResource::slim($content)->resolve(),
                    'moderation' => $moderation === null ? null : [
                        'id' => $moderation->id,
                        'status' => $moderation->status?->value,
                        'company_id' => $moderation->company_id,
                        'submitted_at' => $moderation->submitted_at?->toIso8601String(),
                        'assigned_reviewer_id' => $moderation->assigned_reviewer_id,
                    ],
                ];
            })
            ->all();

        return ApiEnvelope::success(
            ['items' => $items],
            200,
            [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    private function findContent(string $uuid): EditorialContent
    {
        return EditorialContent::query()->where('uuid', $uuid)->firstOrFail();
    }
}
