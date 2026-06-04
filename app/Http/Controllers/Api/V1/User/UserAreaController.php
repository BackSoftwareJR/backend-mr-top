<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\User\ToggleSavedMatchRequest;
use App\Http\Requests\V1\User\UpdateUserProfileRequest;
use App\Http\Requests\V1\User\UpdateUserSearchRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\UserResource;
use App\Http\Resources\V1\UserSearchResource;
use App\Models\Lead;
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
            (int) $request->integer('page', 1),
        );

        $searches = UserSearchResource::collection($paginator->items())->resolve();

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

    public function searchShow(Request $request, Lead $lead): JsonResponse
    {
        return ApiEnvelope::success(
            $this->userAreaService->searchDetail($request->user(), $lead),
        );
    }

    public function updateSearch(UpdateUserSearchRequest $request, Lead $lead): JsonResponse
    {
        return ApiEnvelope::success(
            $this->userAreaService->updateSearchTitle($lead, $request->validated('title')),
        );
    }

    public function attachLead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lead_uuid' => ['required', 'uuid'],
        ]);

        $result = $this->userAreaService->attachLeadToUser(
            $request->user(),
            $validated['lead_uuid'],
        );

        return ApiEnvelope::success([
            'lead' => [
                'uuid' => $result['lead']->uuid,
                'status' => $result['lead']->status->value,
            ],
            'user' => new UserResource($result['user']),
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return ApiEnvelope::success(['user' => new UserResource($request->user())]);
    }

    public function updateProfile(UpdateUserProfileRequest $request): JsonResponse
    {
        $result = $this->userAreaService->updateProfile(
            $request->user(),
            $request->profileAttributes(),
        );

        return ApiEnvelope::success(['user' => new UserResource($result['user'])]);
    }

    public function savedMatches(Request $request): JsonResponse
    {
        return ApiEnvelope::success([
            'ids' => $this->userAreaService->savedMatchIds($request->user()),
        ]);
    }

    public function toggleSavedMatch(ToggleSavedMatchRequest $request): JsonResponse
    {
        return ApiEnvelope::success(
            $this->userAreaService->toggleSavedMatch(
                $request->user(),
                $request->companyId(),
                $request->leadMatchId(),
            ),
        );
    }
}
