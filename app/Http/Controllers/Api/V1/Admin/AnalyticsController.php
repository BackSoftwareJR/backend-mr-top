<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Services\AdminOperationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AdminOperationsService $adminOps,
    ) {}

    public function revenueTimeline(Request $request): JsonResponse
    {
        return ApiEnvelope::success(
            $this->adminOps->revenueTimeline((int) $request->integer('days', 7)),
        );
    }

    public function leadsFlow(Request $request): JsonResponse
    {
        return ApiEnvelope::success(
            $this->adminOps->leadsFlow((int) $request->integer('days', 14)),
        );
    }

    public function portfolioSummary(): JsonResponse
    {
        return ApiEnvelope::success($this->adminOps->portfolioSummary());
    }

    public function portfolioAllocation(): JsonResponse
    {
        return ApiEnvelope::success($this->adminOps->portfolioAllocation());
    }

    public function portfolioPartners(): JsonResponse
    {
        return ApiEnvelope::success($this->adminOps->portfolioPartners());
    }

    public function riskIndicators(): JsonResponse
    {
        return ApiEnvelope::success($this->adminOps->riskIndicators());
    }
}
