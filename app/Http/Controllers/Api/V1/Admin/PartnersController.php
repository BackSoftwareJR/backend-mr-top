<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\ImpersonatePartnerRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\CompanyResource;
use App\Models\Company;
use App\Services\AdminOperationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnersController extends Controller
{
    public function __construct(
        private readonly AdminOperationsService $adminOps,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiEnvelope::success(
            $this->adminOps->listPartners($request->query('stato')),
        );
    }

    public function show(Company $company): JsonResponse
    {
        $detail = $this->adminOps->partnerDetail($company);

        return ApiEnvelope::success([
            'company' => new CompanyResource($detail['company']),
            'documents' => $detail['documents']->map(fn ($d) => [
                'type' => $d->type->value,
                'file_name' => $d->original_name,
                'path' => $d->file_path,
            ])->values()->all(),
            'trust_test' => $detail['trust_test'],
            'trust_score' => $detail['trust_score'],
        ]);
    }

    public function suspend(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        $company = $this->adminOps->suspend(
            $company,
            $request->user(),
            $validated['reason'] ?? null,
            $request,
        );

        return ApiEnvelope::success([
            'company' => new CompanyResource($company),
        ]);
    }

    public function impersonate(ImpersonatePartnerRequest $request, Company $company): JsonResponse
    {
        return ApiEnvelope::success(
            $this->adminOps->impersonate($company, $request->user(), $request),
        );
    }
}
