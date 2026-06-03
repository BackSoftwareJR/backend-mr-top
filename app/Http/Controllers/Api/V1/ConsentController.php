<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Consent\IndexConsentRequest;
use App\Http\Requests\V1\Consent\StoreConsentRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\ConsentLogResource;
use App\Models\ConsentLog;
use App\Services\ConsentLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ConsentController extends Controller
{
    public function __construct(
        private readonly ConsentLogService $consentLogService,
    ) {}

    public function store(StoreConsentRequest $request): JsonResponse
    {
        $logs = $this->consentLogService->recordConsents(
            $request->validated('consents'),
            $request,
            Auth::user(),
        );

        return ApiEnvelope::success(
            ['recorded' => ConsentLogResource::collection($logs)],
            201,
        );
    }

    public function me(IndexConsentRequest $request): JsonResponse
    {
        $user = $request->user();
        $perPage = $request->integer('per_page', 20);

        $latestByType = $this->consentLogService
            ->latestByTypeForUser($user->id)
            ->map(fn (ConsentLog $log) => (new ConsentLogResource($log))->resolve());

        $history = ConsentLog::query()
            ->forUser($user->id)
            ->with('lead')
            ->latestFirst()
            ->paginate($perPage);

        return ApiEnvelope::success(
            [
                'latest_by_type' => $latestByType,
                'history' => ConsentLogResource::collection($history->items()),
            ],
            meta: [
                'page' => $history->currentPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
                'last_page' => $history->lastPage(),
            ],
        );
    }
}
