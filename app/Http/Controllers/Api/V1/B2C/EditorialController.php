<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2C;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\EditorialContentCardResource;
use App\Http\Resources\V1\EditorialContentPublicResource;
use App\Models\EditorialRubric;
use App\Services\Editorial\EditorialContentQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditorialController extends Controller
{
    public function __construct(
        private readonly EditorialContentQueryService $queryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'max:32'],
            'rubric' => ['sometimes', 'string', 'max:80'],
            'featured' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'nullable', 'string', 'max:200'],
            'include_noindex' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $perPage = min(max((int) ($validated['limit'] ?? 20), 1), 50);
        $page = max((int) ($validated['page'] ?? 1), 1);

        $filters = [
            'type' => $validated['type'] ?? null,
            'rubric' => $validated['rubric'] ?? null,
            'featured' => array_key_exists('featured', $validated) ? (bool) $validated['featured'] : null,
            'q' => $validated['q'] ?? null,
            'include_noindex' => (bool) ($validated['include_noindex'] ?? false),
        ];

        $paginator = $this->queryService
            ->publishedList($filters)
            ->paginate(perPage: $perPage, page: $page);

        $contents = collect($paginator->items())
            ->map(fn ($content) => (new EditorialContentCardResource($content))->resolve())
            ->all();

        return ApiEnvelope::success(
            ['contents' => $contents],
            meta: [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function show(string $slug): JsonResponse
    {
        $content = $this->queryService->findPublishedBySlug($slug);

        if ($content === null) {
            abort(404);
        }

        return ApiEnvelope::success([
            'content' => new EditorialContentPublicResource($content),
        ]);
    }

    public function rubrics(): JsonResponse
    {
        $publishedCounts = $this->queryService
            ->publishedCountQuery()
            ->selectRaw('rubric_id, COUNT(*) as aggregate')
            ->groupBy('rubric_id')
            ->pluck('aggregate', 'rubric_id');

        $rubrics = EditorialRubric::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (EditorialRubric $rubric) => [
                'id' => $rubric->id,
                'slug' => $rubric->slug,
                'name' => $rubric->name,
                'description' => $rubric->description,
                'published_count' => (int) ($publishedCounts[$rubric->id] ?? 0),
            ])
            ->values()
            ->all();

        return ApiEnvelope::success(['rubrics' => $rubrics]);
    }
}
