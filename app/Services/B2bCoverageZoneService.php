<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyCoverageZone;

class B2bCoverageZoneService
{
    public function show(Company $company): ?CompanyCoverageZone
    {
        return $company->coverageZone;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsert(Company $company, array $attributes): CompanyCoverageZone
    {
        return CompanyCoverageZone::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'center_lat' => $attributes['center_lat'],
                'center_lng' => $attributes['center_lng'],
                'radius_km' => $attributes['radius_km'],
                'label' => $attributes['label'] ?? null,
                'geocode_place_id' => $attributes['geocode_place_id'] ?? null,
                'geocode_meta' => $attributes['geocode_meta'] ?? null,
            ],
        );
    }

    public function delete(Company $company): bool
    {
        return (bool) $company->coverageZone()?->delete();
    }

    public function companyForUser(\App\Models\User $user): Company
    {
        return $user->companies()->firstOrFail();
    }
}
