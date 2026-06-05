<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\B2B\UpsertCoverageZoneRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\CompanyCoverageZoneResource;
use App\Services\B2bCoverageZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoverageZoneController extends Controller
{
    public function __construct(
        private readonly B2bCoverageZoneService $coverageZoneService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $company = $this->coverageZoneService->companyForUser($request->user());
        $zone = $this->coverageZoneService->show($company);

        return ApiEnvelope::success([
            'coverage_zone' => $zone !== null
                ? new CompanyCoverageZoneResource($zone)
                : null,
        ]);
    }

    public function update(UpsertCoverageZoneRequest $request): JsonResponse
    {
        $company = $this->coverageZoneService->companyForUser($request->user());
        $zone = $this->coverageZoneService->upsert($company, $request->zoneAttributes());

        return ApiEnvelope::success([
            'coverage_zone' => new CompanyCoverageZoneResource($zone),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $company = $this->coverageZoneService->companyForUser($request->user());
        $this->coverageZoneService->delete($company);

        return ApiEnvelope::success(['deleted' => true]);
    }
}
