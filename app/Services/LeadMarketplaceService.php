<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\CrmStatus;
use App\Enums\TransactionType;
use App\Enums\VettingStatus;
use App\Exceptions\AlreadyUnlockedException;
use App\Exceptions\PartnerNotApprovedException;
use App\Models\Company;
use App\Models\LeadMatch;
use App\Models\User;
use App\Models\Wallet;
use App\Support\MarketplaceRef;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadMarketplaceService
{
    private const UNLOCK_COST = 15;

    public function __construct(
        private readonly WalletService $walletService,
        private readonly AuditLogService $auditLogService,
        private readonly ActivityFeedService $activityFeedService,
    ) {}

    /**
     * @return Collection<int, LeadMatch>
     */
    public function listCompatibleLeads(Company $company, bool $unlockedOnly = false): Collection
    {
        $this->assertPartnerApproved($company);

        $query = LeadMatch::query()
            ->with('lead')
            ->where('company_id', $company->id)
            ->where('is_in_marketplace', true)
            ->whereHas('lead', function ($leadQuery) use ($company): void {
                $leadQuery->where('sector_id', $company->sector_id);
            })
            ->orderByDesc('match_score');

        if ($unlockedOnly) {
            $query->whereNotNull('unlocked_at');
        }

        return $query->get()->filter(function (LeadMatch $match) use ($company): bool {
            return $this->isLocationCompatible($company, $match);
        })->values();
    }

    /**
     * @return array{lead_match: LeadMatch, wallet: Wallet}
     */
    public function unlockLead(
        Company $company,
        string $marketplaceRef,
        User $actor,
        ?Request $request = null,
    ): array {
        $this->assertPartnerApproved($company);

        $matchId = MarketplaceRef::parseMatchId($marketplaceRef);

        if ($matchId === null) {
            $leadMatch = LeadMatch::findByExternalRef($marketplaceRef);

            if ($leadMatch === null) {
                abort(404, 'Lead marketplace non trovato.');
            }

            $matchId = $leadMatch->id;
        }

        return DB::transaction(function () use ($company, $matchId, $actor, $request): array {
            $leadMatch = LeadMatch::query()
                ->with('lead')
                ->where('company_id', $company->id)
                ->whereKey($matchId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($leadMatch->unlocked_at !== null) {
                throw new AlreadyUnlockedException;
            }

            $cost = $leadMatch->unlock_cost_credits ?: self::UNLOCK_COST;

            $result = $this->walletService->deductCredits(
                $company,
                $cost,
                TransactionType::LeadUnlock,
                $leadMatch->id,
                'Sblocco lead '.MarketplaceRef::fromMatch($leadMatch),
            );

            $leadMatch->forceFill([
                'unlocked_at' => now(),
                'crm_status' => $leadMatch->crm_status ?? CrmStatus::Nuovo,
            ])->save();

            $metadata = [
                'company_id' => $company->id,
                'lead_match_id' => $leadMatch->id,
                'credits_debited' => $cost,
            ];

            $idempotencyKey = $request?->header('Idempotency-Key');
            if (is_string($idempotencyKey) && $idempotencyKey !== '') {
                $metadata['idempotency_key'] = $idempotencyKey;
            }

            $this->auditLogService->record(
                AuditAction::LeadUnlocked,
                $actor,
                $leadMatch,
                $metadata,
                $request,
            );

            $this->activityFeedService->recordLeadUnlock(
                $company,
                $leadMatch,
                $result['transaction'],
            );

            return [
                'lead_match' => $leadMatch->fresh(['lead']),
                'wallet' => $result['wallet'],
            ];
        });
    }

    public function resolveCompanyForUser(User $user): Company
    {
        $company = $user->companies()->first();

        if ($company === null) {
            abort(403, 'Nessuna azienda associata al tuo account.');
        }

        return $company;
    }

    private function assertPartnerApproved(Company $company): void
    {
        if ($company->vetting_status !== VettingStatus::Approved) {
            throw new PartnerNotApprovedException;
        }
    }

    private function isLocationCompatible(Company $company, LeadMatch $match): bool
    {
        $lead = $match->lead;

        if ($lead === null || $lead->location_label === null) {
            return true;
        }

        if ($company->city !== null && str_contains(strtolower($lead->location_label), strtolower($company->city))) {
            return true;
        }

        $serviceAreas = $company->dynamic_attributes['service_areas'] ?? null;

        if (is_array($serviceAreas)) {
            $leadLocation = strtolower($lead->location_label);

            foreach ($serviceAreas as $area) {
                if (is_string($area) && str_contains($leadLocation, strtolower($area))) {
                    return true;
                }
            }
        }

        return $company->city === null && empty($serviceAreas);
    }
}
