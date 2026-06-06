<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\EditorialContent;
use App\Services\Editorial\EditorialAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class B2BEditorialAnalyticsController extends Controller
{
    public function __construct(
        private readonly EditorialAnalyticsService $analyticsService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user?->companies()->value('companies.id');

        if ($companyId === null) {
            abort(403);
        }

        $this->authorize('viewAny', EditorialContent::class);

        [$from, $to] = $this->analyticsService->resolveDateRange(
            $request->query('from'),
            $request->query('to'),
        );

        return ApiEnvelope::success(
            $this->analyticsService->getCompanyStats((int) $companyId, $from, $to),
        );
    }
}
