<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\UpdateErasureRequestRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\DataErasureRequest;
use App\Services\PrivacyErasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacyErasureController extends Controller
{
    public function __construct(
        private readonly PrivacyErasureService $privacyErasureService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        return ApiEnvelope::success(
            $this->privacyErasureService->listAdminQueue($perPage),
        );
    }

    public function update(UpdateErasureRequestRequest $request, int $id): JsonResponse
    {
        $erasureRequest = DataErasureRequest::query()->findOrFail($id);
        $validated = $request->validated();

        $result = $this->privacyErasureService->reviewErasureRequest(
            $erasureRequest,
            $validated['action'],
            $request->user(),
            $validated['notes'] ?? null,
            $request,
        );

        return ApiEnvelope::success([
            'erasure_request' => $this->privacyErasureService->formatAdminErasureRequest(
                $result['erasure_request']->loadMissing('user'),
            ),
        ]);
    }
}
