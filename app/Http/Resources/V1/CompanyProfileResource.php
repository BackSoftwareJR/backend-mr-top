<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * B2B consumer-facing company profile (match card fields).
 */
class CompanyProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CompanyProfile $profile */
        $profile = $this->resource;

        return [
            'display_name' => $profile->display_name,
            'service_type' => $profile->service_type,
            'tagline' => $profile->tagline,
            'description' => $profile->description,
            'pros' => $profile->pros ?? [],
            'image_url' => $profile->image_url,
            'location_label' => $profile->location_label,
            'contact_hint' => $profile->contact_hint,
        ];
    }
}
