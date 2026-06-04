<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\B2B\IndexMarketplaceRequest;
use App\Http\Requests\V1\B2B\UnlockLeadRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\CrmClientResource;
use App\Http\Resources\V1\LeadPreviewResource;
use App\Http\Resources\V1\LeadUnlockedResource;
use App\Http\Resources\V1\WalletResource;
use App\Services\LeadMarketplaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LeadMarketplaceController extends Controller
{
    public function __construct(
        private readonly LeadMarketplaceService $leadMarketplaceService,
    ) {}

    public function index(IndexMarketplaceRequest $request): JsonResponse
    {
        $company = $this->leadMarketplaceService->resolveCompanyForUser(Auth::user());
        $unlockedOnly = $request->boolean('unlocked_only');

        $matches = $this->leadMarketplaceService->listCompatibleLeads($company, $unlockedOnly);

        $perPage = 20;
        $page = max(1, (int) $request->input('page', 1));
        $paginated = $matches->forPage($page, $perPage)->values();

        $leads = $paginated->map(function ($match) {
            if ($match->unlocked_at !== null) {
                return new LeadUnlockedResource($match);
            }

            return new LeadPreviewResource($match);
        });

        return ApiEnvelope::success([
            'leads' => $leads,
        ], 200, [
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $matches->count(),
                'last_page' => (int) max(1, ceil($matches->count() / $perPage)),
            ],
        ]);
    }

    public function unlock(UnlockLeadRequest $request, string $id): JsonResponse
    {
        $company = $this->leadMarketplaceService->resolveCompanyForUser(Auth::user());

        $result = $this->leadMarketplaceService->unlockLead($company, $id, $request->user(), $request);

        return ApiEnvelope::success([
            'lead' => new LeadUnlockedResource($result['lead_match']),
            'wallet' => new WalletResource($result['wallet']),
            'crm_client' => new CrmClientResource($result['lead_match']),
        ]);
    }
}
