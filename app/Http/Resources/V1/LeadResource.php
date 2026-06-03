<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Consumer-facing lead summary.
 *
 * API contract:
 * - uuid: RFC 4122 lead identifier
 * - public_ref: LD-{digits} marketplace reference (nullable until assigned)
 * - status: draft | processing | routed | assigned | closed | cancelled
 * - location_label, need_summary, budget_min, budget_max: optional summary fields
 * - matches: LeadMatchResource collection when loaded
 *
 * Hidden: internal id, sector_id, user_id, payload (PII), contact_* fields, admin_notes
 */
class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Lead $lead */
        $lead = $this->resource;

        return [
            'uuid' => $lead->uuid,
            'public_ref' => $lead->public_ref,
            'status' => $lead->status?->value ?? $lead->status,
            'location_label' => $this->when(isset($lead->location_label), $lead->location_label),
            'need_summary' => $this->when(isset($lead->need_summary), $lead->need_summary),
            'budget_min' => $this->when(isset($lead->budget_min), $lead->budget_min),
            'budget_max' => $this->when(isset($lead->budget_max), $lead->budget_max),
            'created_at' => $this->when(
                $lead->created_at !== null,
                fn () => $lead->created_at?->toIso8601String(),
            ),
            'updated_at' => $this->when(
                $lead->updated_at !== null,
                fn () => $lead->updated_at?->toIso8601String(),
            ),
            'matches' => LeadMatchResource::collection($this->whenLoaded('leadMatches')),
        ];
    }
}
