<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Lead;
use App\Models\LeadMatch;
use App\Support\MarketplaceRef;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Marketplace lead with full PII after unlock.
 */
class LeadUnlockedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LeadMatch $match */
        $match = $this->resource;
        /** @var Lead $lead */
        $lead = $match->lead;

        return [
            'id' => MarketplaceRef::fromMatch($match),
            'match_score' => $match->match_score,
            'budget' => $this->formatBudget($lead),
            'location' => $lead->location_label,
            'need' => $lead->need_summary,
            'unlock_cost' => $match->unlock_cost_credits,
            'unlocked' => true,
            'name' => $lead->contact_name,
            'phone' => $lead->contact_phone,
            'email' => $lead->contact_email,
        ];
    }

    private function formatBudget(Lead $lead): ?string
    {
        if ($lead->budget_min === null && $lead->budget_max === null) {
            return null;
        }

        $avg = (int) round((($lead->budget_min ?? 0) + ($lead->budget_max ?? 0)) / 2);

        return number_format($avg, 0, ',', '.').'€';
    }
}
