<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InterestAreaType;
use App\Models\Lead;
use App\Models\LeadInterestArea;

final class LeadInterestAreaService
{
    private const DEFAULT_RADIUS_KM = 15.0;

    public function __construct(
        private readonly PhotonGeocodingService $photon = new PhotonGeocodingService,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $areas
     */
    public function syncFromPayload(Lead $lead, array $areas, ?string $locationLabel = null): void
    {
        $lead->interestAreas()->delete();

        if ($areas !== []) {
            $this->createMany($lead, $areas);

            return;
        }

        if ($locationLabel !== null && trim($locationLabel) !== '') {
            $this->createDefaultFromLabel($lead, $locationLabel);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $areas
     */
    private function createMany(Lead $lead, array $areas): void
    {
        foreach (array_values($areas) as $index => $area) {
            $type = InterestAreaType::tryFrom((string) ($area['type'] ?? ''));

            if ($type === null) {
                continue;
            }

            $attributes = [
                'lead_id' => $lead->id,
                'type' => $type,
                'sort_order' => $index,
                'label' => isset($area['label']) ? (string) $area['label'] : null,
            ];

            if ($type === InterestAreaType::Circle) {
                $attributes['center_lat'] = (float) ($area['center_lat'] ?? $area['centerLat'] ?? 0);
                $attributes['center_lng'] = (float) ($area['center_lng'] ?? $area['centerLng'] ?? 0);
                $attributes['radius_km'] = (float) ($area['radius_km'] ?? $area['radiusKm'] ?? self::DEFAULT_RADIUS_KM);
            } else {
                $geometry = $area['geometry'] ?? $area['geometry_json'] ?? null;
                $attributes['geometry_json'] = is_array($geometry) ? $geometry : null;
                $attributes['center_lat'] = isset($area['center_lat']) ? (float) $area['center_lat'] : null;
                $attributes['center_lng'] = isset($area['center_lng']) ? (float) $area['center_lng'] : null;
            }

            LeadInterestArea::query()->create($attributes);
        }
    }

    private function createDefaultFromLabel(Lead $lead, string $label): void
    {
        $geocoded = $this->photon->geocode($label);

        if ($geocoded === null) {
            return;
        }

        LeadInterestArea::query()->create([
            'lead_id' => $lead->id,
            'type' => InterestAreaType::Circle,
            'center_lat' => $geocoded['lat'],
            'center_lng' => $geocoded['lng'],
            'radius_km' => self::DEFAULT_RADIUS_KM,
            'label' => $geocoded['label'] ?: $label,
            'sort_order' => 0,
        ]);
    }
}
