<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\B2B\UpdateCompanyProfileRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\CompanyProfileResource;
use App\Http\Resources\V1\CompanyResource;
use App\Services\B2bCompanyProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyProfileController extends Controller
{
    public function __construct(
        private readonly B2bCompanyProfileService $companyProfileService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $result = $this->companyProfileService->show($request->user());

        return ApiEnvelope::success([
            'company' => new CompanyResource($result['company']),
            'profile' => $result['profile'] !== null
                ? new CompanyProfileResource($result['profile'])
                : null,
        ]);
    }

    public function update(UpdateCompanyProfileRequest $request): JsonResponse
    {
        $result = $this->companyProfileService->update(
            $request->user(),
            $request->profileAttributes(),
        );

        return ApiEnvelope::success([
            'company' => new CompanyResource($result['company']),
            'profile' => new CompanyProfileResource($result['profile']),
        ]);
    }
}
