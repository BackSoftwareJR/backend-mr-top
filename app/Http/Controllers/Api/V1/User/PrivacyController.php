<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\User\StoreEraseRequestRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Services\PrivacyErasureService;
use App\Services\PrivacyExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    public function __construct(
        private readonly PrivacyErasureService $privacyErasureService,
        private readonly PrivacyExportService $privacyExportService,
    ) {}

    public function export(Request $request): JsonResponse
    {
        return ApiEnvelope::success(
            $this->privacyExportService->exportForUser($request->user(), $request),
        );
    }

    public function eraseRequest(StoreEraseRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->privacyErasureService->submitEraseRequest(
            $request->user(),
            $validated['reason'] ?? null,
            $request,
        );

        $erasureRequest = $result['erasure_request'];

        return ApiEnvelope::success([
            'erasure_request' => [
                'id' => $erasureRequest->id,
                'status' => $erasureRequest->status->value,
                'requested_at' => $erasureRequest->requested_at?->toIso8601String(),
                'message' => 'Richiesta registrata. Ti contatteremo entro 30 giorni a hola@wenando.com.',
            ],
        ], 201);
    }
}
