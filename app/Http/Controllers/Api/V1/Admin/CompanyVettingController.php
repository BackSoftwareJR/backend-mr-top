<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\ApproveCompanyRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\CompanyResource;
use App\Models\Company;
use App\Services\PartnerApprovalService;
use Illuminate\Http\JsonResponse;

class CompanyVettingController extends Controller
{
    public function __construct(
        private readonly PartnerApprovalService $partnerApprovalService,
    ) {}

    public function approve(ApproveCompanyRequest $request, Company $company): JsonResponse
    {
        $company = $this->partnerApprovalService->approve(
            $company,
            $request->user(),
            $request,
        );

        return ApiEnvelope::success([
            'company' => new CompanyResource($company),
        ]);
    }
}
