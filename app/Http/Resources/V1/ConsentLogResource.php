<?php

namespace App\Http\Resources\V1;

use App\Models\ConsentLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Consent audit record exposed to authenticated consumers.
 *
 * API contract:
 * - id: internal audit id (integer)
 * - consent_type: privacy_policy | terms_b2c | marketing | analytics_cookies | lead_sharing
 * - policy_version: semver string (e.g. 1.0.0)
 * - consent_given: boolean
 * - created_at: ISO-8601 timestamp
 * - lead: nested LeadResource when relationship loaded (uuid + public_ref only)
 *
 * Hidden from API: user_id, session_id, ip_address, user_agent, consent_text_hash, metadata, deleted_at
 */
class ConsentLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ConsentLog $log */
        $log = $this->resource;

        return [
            'id' => $log->id,
            'consent_type' => $log->consent_type->value,
            'policy_version' => $log->policy_version,
            'consent_given' => $log->consent_given,
            'created_at' => $log->created_at?->toIso8601String(),
            'lead' => LeadResource::make($this->whenLoaded('lead')),
        ];
    }
}
