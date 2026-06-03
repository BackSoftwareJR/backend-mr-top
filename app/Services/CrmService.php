<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CrmStatus;
use App\Models\Company;
use App\Models\LeadMatch;
use App\Support\MarketplaceRef;
use Illuminate\Database\Eloquent\Collection;

class CrmService
{
    /**
     * @return Collection<int, LeadMatch>
     */
    public function listClients(Company $company, ?CrmStatus $status = null): Collection
    {
        $query = LeadMatch::query()
            ->with('lead')
            ->where('company_id', $company->id)
            ->whereNotNull('unlocked_at')
            ->orderByDesc('unlocked_at');

        if ($status !== null) {
            $query->where('crm_status', $status);
        }

        return $query->get();
    }

    public function updateStatus(Company $company, string $clientRef, CrmStatus $status): LeadMatch
    {
        $matchId = MarketplaceRef::parseCrmClientId($clientRef);

        if ($matchId === null) {
            abort(404, 'Cliente CRM non trovato.');
        }

        $leadMatch = LeadMatch::query()
            ->with('lead')
            ->where('company_id', $company->id)
            ->whereKey($matchId)
            ->whereNotNull('unlocked_at')
            ->firstOrFail();

        $leadMatch->forceFill(['crm_status' => $status])->save();

        return $leadMatch->fresh(['lead']);
    }
}
