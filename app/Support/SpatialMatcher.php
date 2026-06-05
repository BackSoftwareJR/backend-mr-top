<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\InterestAreaType;
use App\Models\CompanyCoverageZone;
use App\Models\LeadInterestArea;

final class SpatialMatcher
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function coverageOverlapsInterestArea(CompanyCoverageZone $coverage, LeadInterestArea $interest): bool
    {
        $centerLat = (float) $coverage->center_lat;
        $centerLng = (float) $coverage->center_lng;
        $radiusKm = (float) $coverage->radius_km;

        if ($interest->type === InterestAreaType::Circle) {
            return $this->circlesOverlap(
                $centerLat,
                $centerLng,
                $radiusKm,
                (float) $interest->center_lat,
                (float) $interest->center_lng,
                (float) $interest->radius_km,
            );
        }

        $ring = $this->polygonRing($interest->geometry_json);

        if ($ring === null) {
            return false;
        }

        return $this->circleIntersectsPolygon($centerLat, $centerLng, $radiusKm, $ring);
    }

    public function haversineDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function circlesOverlap(
        float $lat1,
        float $lng1,
        float $radius1Km,
        float $lat2,
        float $lng2,
        float $radius2Km,
    ): bool {
        if ($radius1Km <= 0 || $radius2Km <= 0) {
            return false;
        }

        return $this->haversineDistanceKm($lat1, $lng1, $lat2, $lng2) <= ($radius1Km + $radius2Km);
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring  [lng, lat] pairs
     */
    public function pointInPolygon(float $lat, float $lng, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        if ($count < 3) {
            return false;
        }

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $ring[$i][0];
            $yi = $ring[$i][1];
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];

            $intersects = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi);

            if ($intersects) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring  [lng, lat] pairs
     */
    public function circleIntersectsPolygon(float $centerLat, float $centerLng, float $radiusKm, array $ring): bool
    {
        if ($radiusKm <= 0 || count($ring) < 3) {
            return false;
        }

        if ($this->pointInPolygon($centerLat, $centerLng, $ring)) {
            return true;
        }

        foreach ($ring as [$lng, $lat]) {
            if ($this->haversineDistanceKm($centerLat, $centerLng, $lat, $lng) <= $radiusKm) {
                return true;
            }
        }

        $count = count($ring);

        for ($i = 0; $i < $count; $i++) {
            $j = ($i + 1) % $count;
            [$lng1, $lat1] = $ring[$i];
            [$lng2, $lat2] = $ring[$j];

            if ($this->pointToSegmentDistanceKm($centerLat, $centerLng, $lat1, $lng1, $lat2, $lng2) <= $radiusKm) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $geometry
     * @return list<array{0: float, 1: float}>|null [lng, lat]
     */
    public function polygonRing(?array $geometry): ?array
    {
        if ($geometry === null) {
            return null;
        }

        $coordinates = $geometry['coordinates'] ?? null;

        if (! is_array($coordinates)) {
            return null;
        }

        if (($geometry['type'] ?? null) === 'Polygon') {
            $ring = $coordinates[0] ?? null;
        } elseif (($geometry['type'] ?? null) === 'MultiPolygon') {
            $ring = $coordinates[0][0] ?? null;
        } else {
            return null;
        }

        if (! is_array($ring) || count($ring) < 3) {
            return null;
        }

        $normalized = [];

        foreach ($ring as $point) {
            if (! is_array($point) || count($point) < 2) {
                continue;
            }

            $normalized[] = [(float) $point[0], (float) $point[1]];
        }

        return count($normalized) >= 3 ? $normalized : null;
    }

    private function pointToSegmentDistanceKm(
        float $pointLat,
        float $pointLng,
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
    ): float {
        $distance12 = $this->haversineDistanceKm($lat1, $lng1, $lat2, $lng2);

        if ($distance12 <= 1e-9) {
            return $this->haversineDistanceKm($pointLat, $pointLng, $lat1, $lng1);
        }

        $best = min(
            $this->haversineDistanceKm($pointLat, $pointLng, $lat1, $lng1),
            $this->haversineDistanceKm($pointLat, $pointLng, $lat2, $lng2),
        );

        $steps = 8;

        for ($step = 1; $step < $steps; $step++) {
            $t = $step / $steps;
            $midLat = $lat1 + ($lat2 - $lat1) * $t;
            $midLng = $lng1 + ($lng2 - $lng1) * $t;
            $best = min($best, $this->haversineDistanceKm($pointLat, $pointLng, $midLat, $midLng));
        }

        return $best;
    }
}
