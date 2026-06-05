<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\CompanyCoverageZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CompanyCoverageZone */
class CompanyCoverageZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'center_lat' => (float) $this->center_lat,
            'center_lng' => (float) $this->center_lng,
            'radius_km' => (float) $this->radius_km,
            'label' => $this->label,
            'geocode_place_id' => $this->geocode_place_id,
            'geocode_meta' => $this->geocode_meta,
        ];
    }
}
