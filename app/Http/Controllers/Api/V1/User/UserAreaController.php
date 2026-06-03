<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\UserResource;
use App\Services\UserAreaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAreaController extends Controller
{
    public function __construct(
        private readonly UserAreaService $userAreaService,
    ) {}

    public function home(Request $request): JsonResponse
    {
        return ApiEnvelope::success($this->userAreaService->home($request->user()));
    }

    public function searches(Request $request): JsonResponse
    {
        $paginator = $this->userAreaService->searches(
            $request->user(),
            (int) $request->integer('per_page', 20),
        );

        $searches = collect($paginator->items())->map(function ($lead) {
            return [
                'id' => $lead->id,
                'title' => $lead->need_summary,
                'location' => $lead->location_label,
                'date' => $lead->created_at?->toDateString(),
                'status' => $lead->status->value === 'processing' ? 'processing' : 'completed',
                'match_count' => $lead->match_count ?? 0,
                'answers' => $lead->payload,
            ];
        })->all();

        return ApiEnvelope::success(
            ['searches' => $searches],
            200,
            [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function searchShow(Request $request, int $id): JsonResponse
    {
        return ApiEnvelope::success(
            $this->userAreaService->searchDetail($request->user(), $id),
        );
    }

    public function attachLead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lead_uuid' => ['required', 'uuid'],
        ]);

        $lead = $this->userAreaService->attachLeadToUser(
            $request->user(),
            $validated['lead_uuid'],
        );

        return ApiEnvelope::success(['lead' => ['uuid' => $lead->uuid, 'status' => $lead->status->value]]);
    }

    public function profile(Request $request): JsonResponse
    {
        return ApiEnvelope::success(['user' => new UserResource($request->user())]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);

        $result = $this->userAreaService->updateProfile(
            $request->user(),
            $validated['name'] ?? null,
            $validated['phone'] ?? null,
        );

        return ApiEnvelope::success(['user' => new UserResource($result['user'])]);
    }

    public function savedMatches(Request $request): JsonResponse
    {
        return ApiEnvelope::success([
            'ids' => $this->userAreaService->savedMatchIds($request->user()),
        ]);
    }

    public function toggleSavedMatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'lead_match_id' => ['nullable', 'integer', 'exists:lead_matches,id'],
        ]);

        return ApiEnvelope::success(
            $this->userAreaService->toggleSavedMatch(
                $request->user(),
                $validated['company_id'] ?? null,
                $validated['lead_match_id'] ?? null,
            ),
        );
    }
}
