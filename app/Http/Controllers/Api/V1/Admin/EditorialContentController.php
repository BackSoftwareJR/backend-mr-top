<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\StoreEditorialContentRequest;
use App\Http\Requests\V1\Admin\UpdateEditorialContentRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\EditorialContentResource;
use App\Models\EditorialContent;
use App\Services\Editorial\EditorialContentService;
use App\Services\Editorial\EditorialOptimisticLock;
use App\Services\Editorial\EditorialPreviewService;
use App\Services\Editorial\SuggestInternalLinksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditorialContentController extends Controller
{
    public function __construct(
        private readonly EditorialContentService $editorialContentService,
        private readonly EditorialOptimisticLock $optimisticLock,
        private readonly EditorialPreviewService $previewService,
        private readonly SuggestInternalLinksService $suggestInternalLinksService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('viewAny', EditorialContent::class);

        $query = EditorialContent::query()->with('rubric');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

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

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where('title', 'like', $term);
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $paginator = $query->orderByDesc('updated_at')->paginate($perPage);

        $contents = collect($paginator->items())
            ->map(fn (EditorialContent $content) => EditorialContentResource::slim($content)->resolve())
            ->all();

        return ApiEnvelope::success(
            ['contents' => $contents],
            200,
            [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function store(StoreEditorialContentRequest $request): JsonResponse
    {
        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('create', EditorialContent::class);

        $content = $this->editorialContentService->create(
            $request->validated(),
            $request->user(),
        );

        return ApiEnvelope::success([
            'content' => new EditorialContentResource($content->load('rubric')),
        ], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $content = $this->findContent($uuid);

        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('view', $content);

        return ApiEnvelope::success([
            'content' => new EditorialContentResource($content->load('rubric')),
        ]);
    }

    public function update(UpdateEditorialContentRequest $request, string $uuid): JsonResponse
    {
        $content = $this->findContent($uuid);

        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('update', $content);
        $this->optimisticLock->assertVersionMatches($content, $request);

        $content = $this->editorialContentService->update(
            $content,
            $request->validated(),
            $request->user(),
        );

        return ApiEnvelope::success([
            'content' => new EditorialContentResource($content->load('rubric')),
        ]);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $content = $this->findContent($uuid);

        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('delete', $content);

        $this->editorialContentService->delete($content);

        return ApiEnvelope::success([
            'deleted' => true,
            'uuid' => $uuid,
        ]);
    }

    public function storeRevision(Request $request, string $uuid): JsonResponse
    {
        $request->validate([
            'change_summary' => ['nullable', 'string', 'max:500'],
        ]);

        $content = $this->findContent($uuid);

        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('update', $content);

        $revision = $this->editorialContentService->createRevisionSnapshot(
            $content,
            $request->user(),
            $request->input('change_summary'),
        );

        return ApiEnvelope::success([
            'revision' => [
                'id' => $revision->id,
                'revision_number' => $revision->revision_number,
                'change_summary' => $revision->change_summary,
                'created_at' => $revision->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function listRevisions(string $uuid): JsonResponse
    {
        $content = $this->findContent($uuid);

        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('view', $content);

        $revisions = $content->revisions()
            ->orderByDesc('revision_number')
            ->get()
            ->map(fn ($revision) => [
                'id' => $revision->id,
                'revision_number' => $revision->revision_number,
                'change_summary' => $revision->change_summary,
                'created_by_user_id' => $revision->created_by_user_id,
                'created_at' => $revision->created_at?->toIso8601String(),
            ])
            ->all();

        return ApiEnvelope::success([
            'revisions' => $revisions,
        ]);
    }

    public function previewToken(string $uuid): JsonResponse
    {
        $content = $this->findContent($uuid);

        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('view', $content);

        $issued = $this->previewService->generate($content->uuid);

        return ApiEnvelope::success([
            'preview_url' => $this->previewService->previewUrl($content->uuid, $issued['token']),
            'expires_at' => $issued['expires_at']->toIso8601String(),
        ]);
    }

    public function suggestedLinks(string $uuid): JsonResponse
    {
        $content = $this->findContent($uuid);

        $this->authorize('accessAdmin', EditorialContent::class);
        $this->authorize('view', $content);

        $suggestions = $this->suggestInternalLinksService->storedSuggestions($content);

        if ($suggestions === []) {
            $suggestions = $this->suggestInternalLinksService->suggest($content);
        }

        return ApiEnvelope::success([
            'suggestions' => $suggestions,
        ]);
    }

    private function findContent(string $uuid): EditorialContent
    {
        return EditorialContent::query()->where('uuid', $uuid)->firstOrFail();
    }
}
