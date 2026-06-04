<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Consumer search history projection from {@see Lead}.
 */
class UserSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Lead $lead */
        $lead = $this->resource;
        $matchCount = $lead->match_count
            ?? $lead->leadMatches()
                ->where('is_visible_to_consumer', true)
                ->count();

        return [
            'id' => $lead->id,
            'uuid' => $lead->uuid,
            'lead_uuid' => $lead->uuid,
            'public_ref' => $lead->public_ref,
            'title' => $lead->displayTitle(),
            'location' => $lead->location_label,
            'date' => $lead->created_at?->toDateString(),
            'status' => $lead->status === LeadStatus::Processing ? 'processing' : 'completed',
            'match_count' => $matchCount,
            'answers' => $lead->payload,
        ];
    }
}
