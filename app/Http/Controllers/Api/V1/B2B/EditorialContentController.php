<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\B2B\StoreB2bEditorialContentRequest;
use App\Http\Requests\V1\B2B\UpdateB2bEditorialContentRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\EditorialContentResource;
use App\Models\EditorialContent;
use App\Models\User;
use App\Services\Editorial\EditorialContentService;
use App\Services\Editorial\EditorialOptimisticLock;
use App\Services\Editorial\EditorialWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditorialContentController extends Controller
{
    public function __construct(
        private readonly EditorialContentService $editorialContentService,
        private readonly EditorialWorkflowService $workflowService,
        private readonly EditorialOptimisticLock $optimisticLock,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EditorialContent::class);

        $companyId = $this->resolveCompanyId($request->user());

        $query = EditorialContent::query()
            ->with(['rubric', 'company'])
            ->where('company_id', $companyId)
            ->where('author_type', EditorialAuthorType::Company);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('type')) {
            $query->where('content_type', $request->string('type')->toString());
        }

        if ($request->filled('rubric_id')) {
            $query->where('rubric_id', $request->integer('rubric_id'));
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $paginator = $query->orderByDesc('updated_at')->paginate($perPage);

        $contents = collect($paginator->items())
            ->map(fn (EditorialContent $content) => EditorialContentResource::forPartner($content)->resolve())
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

    public function store(StoreB2bEditorialContentRequest $request): JsonResponse
    {
        $this->authorize('create', EditorialContent::class);

        $companyId = $this->resolveCompanyId($request->user());

        $content = $this->editorialContentService->create(
            array_merge($request->validated(), [
                'author_type' => EditorialAuthorType::Company->value,
                'company_id' => $companyId,
            ]),
            $request->user(),
        );

        return ApiEnvelope::success([
            'content' => EditorialContentResource::forPartner($content->load(['rubric', 'company'])),
        ], 201);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $content = $this->findOwnContent($uuid, $request->user());

        return ApiEnvelope::success([
            'content' => EditorialContentResource::forPartner($content->load(['rubric', 'company'])),
        ]);
    }

    public function update(UpdateB2bEditorialContentRequest $request, string $uuid): JsonResponse
    {
        $content = $this->findOwnContent($uuid, $request->user());

        $this->authorize('update', $content);
        $this->optimisticLock->assertVersionMatches($content, $request);

        $content = $this->editorialContentService->update(
            $content,
            $request->validated(),
            $request->user(),
        );

        return ApiEnvelope::success([
            'content' => EditorialContentResource::forPartner($content->load(['rubric', 'company'])),
        ]);
    }

    public function submit(Request $request, string $uuid): JsonResponse
    {
        $content = $this->findOwnContent($uuid, $request->user());

        $this->authorize('transition', [$content, EditorialContentStatus::PendingReview]);
        $this->optimisticLock->assertVersionMatches($content, $request);

        if ($content->status !== EditorialContentStatus::Draft) {
            throw new ApiException(
                'INVALID_TRANSITION',
                'Solo le bozze possono essere inviate in revisione.',
                422,
            );
        }

        $content = $this->workflowService->transition(
            $content,
            EditorialContentStatus::PendingReview,
            $request->user(),
            $request->input('note'),
        );

        return ApiEnvelope::success([
            'content' => EditorialContentResource::forPartner($content),
        ]);
    }

    private function findOwnContent(string $uuid, User $user): EditorialContent
    {
        $content = EditorialContent::query()->where('uuid', $uuid)->firstOrFail();

        $this->authorize('view', $content);

        return $content;
    }

    private function resolveCompanyId(User $user): int
    {
        $companyId = $user->companies()->value('companies.id');

        if ($companyId === null) {
            abort(403, 'Nessuna struttura associata all\'utente.');
        }

        return (int) $companyId;
    }
}
