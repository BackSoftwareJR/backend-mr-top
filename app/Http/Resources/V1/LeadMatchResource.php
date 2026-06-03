<?php

namespace App\Http\Resources\V1;

use App\Models\LeadMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lead-to-company match surfaced in consumer results or B2B marketplace.
 *
 * API contract:
 * - id: match uuid (uses lead_match id as string until dedicated uuid column exists)
 * - match_score: 0–100 compatibility score
 * - rank: display ordering
 * - unlocked: whether partner unlocked contact details
 * - unlock_cost: credits required (marketplace)
 * - company: nested CompanyResource when loaded
 *
 * Hidden: internal ids, metadata, crm_status (B2B CRM uses separate view)
 */
class LeadMatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LeadMatch $match */
        $match = $this->resource;

        return [
            'id' => (string) $match->id,
            'match_score' => $match->match_score,
            'rank' => $match->rank,
            'unlocked' => $match->unlocked_at !== null,
            'unlock_cost' => $match->unlock_cost_credits,
            'company' => CompanyResource::make($this->whenLoaded('company')),
        ];
    }
}
