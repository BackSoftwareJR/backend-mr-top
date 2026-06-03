<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\CompanyResource;
use App\Services\B2bOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyProfileController extends Controller
{
    public function __construct(
        private readonly B2bOnboardingService $onboardingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());

        return ApiEnvelope::success(['company' => new CompanyResource($company)]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_name' => ['sometimes', 'string', 'max:255'],
            'legal_name' => ['sometimes', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:128'],
            'dynamic' => ['sometimes', 'array'],
            'schedule' => ['sometimes', 'array'],
        ]);

        $company = $this->onboardingService->companyForUser($request->user());
        $company->update($validated);

        return ApiEnvelope::success(['company' => new CompanyResource($company->fresh())]);
    }
}
