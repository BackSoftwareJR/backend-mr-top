<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\DashboardStatsResource;
use App\Services\AdminDashboardService;
use Illuminate\Http\JsonResponse;

class DashboardStatsController extends Controller
{
    public function __construct(
        private readonly AdminDashboardService $adminDashboardService,
    ) {}

    public function index(): JsonResponse
    {
        $stats = $this->adminDashboardService->stats();

        return ApiEnvelope::success(new DashboardStatsResource($stats));
    }
}
