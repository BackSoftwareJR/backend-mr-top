<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\RejectPartnerRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\CompanyResource;
use App\Models\Company;
use App\Services\PartnerApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerApprovalController extends Controller
{
    public function __construct(
        private readonly PartnerApprovalService $partnerApprovalService,
    ) {}

    public function approve(Request $request, Company $company): JsonResponse
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

    public function reject(RejectPartnerRequest $request, Company $company): JsonResponse
    {
        $company = $this->partnerApprovalService->reject(
            $company,
            $request->user(),
            $request->input('reason'),
            $request,
        );

        return ApiEnvelope::success([
            'company' => new CompanyResource($company),
        ]);
    }
}
