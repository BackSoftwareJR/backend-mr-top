<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\EditorialContent;
use App\Services\Editorial\EditorialMetricsService;
use Illuminate\Http\JsonResponse;

class AdminEditorialMetricsController extends Controller
{
    public function __construct(
        private readonly EditorialMetricsService $metricsService,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('accessAdmin', EditorialContent::class);

        return ApiEnvelope::success($this->metricsService->aggregate());
    }
}
