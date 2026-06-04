<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Enums\CrmStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\B2B\IndexCrmClientsRequest;
use App\Http\Requests\V1\B2B\UpdateCrmStatusRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\CrmClientResource;
use App\Services\CrmService;
use App\Services\LeadMarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SmartCrmController extends Controller
{
    public function __construct(
        private readonly CrmService $crmService,
        private readonly LeadMarketplaceService $leadMarketplaceService,
    ) {}

    public function index(IndexCrmClientsRequest $request): JsonResponse
    {
        $company = $this->leadMarketplaceService->resolveCompanyForUser(Auth::user());

        $status = $request->filled('stato')
            ? CrmStatus::from($request->string('stato')->toString())
            : null;

        $clients = $this->crmService->listClients($company, $status);

        return ApiEnvelope::success([
            'clients' => CrmClientResource::collection($clients),
        ]);
    }

    public function updateStatus(UpdateCrmStatusRequest $request, string $id): JsonResponse
    {
        $company = $this->leadMarketplaceService->resolveCompanyForUser(Auth::user());

        $client = $this->crmService->updateStatus(
            $company,
            $id,
            $request->crmStatus(),
            $request->user(),
            $request,
        );

        return ApiEnvelope::success([
            'client' => new CrmClientResource($client),
        ]);
    }
}
