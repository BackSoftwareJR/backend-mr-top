<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

final class PhotonGeocodingService
{
    private const BASE_URL = 'https://photon.komoot.io/api/';

    private const ITALY_CENTER = ['lat' => 41.9, 'lng' => 12.5];

    /**
     * @return array{lat: float, lng: float, label: string, meta: array<string, mixed>}|null
     */
    public function geocode(string $query, ?string $country = 'it'): ?array
    {
        $features = $this->searchFeatures($query, 1, $country);

        if ($features === []) {
            return null;
        }

        return $this->mapFeature($features[0]);
    }

    /**
     * @return array{lat: float, lng: float, label: string, meta: array<string, mixed>}|null
     */
    public function reverse(float $lat, float $lng): ?array
    {
        $response = Http::timeout(5)->acceptJson()->get(self::BASE_URL, [
            'lat' => $lat,
            'lon' => $lng,
            'lang' => 'it',
        ]);

        if (! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $response->json();
        $features = is_array($payload['features'] ?? null) ? $payload['features'] : [];

        if ($features === []) {
            return null;
        }

        return $this->mapFeature($features[0]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchFeatures(string $query, int $limit = 10, ?string $country = 'it'): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $params = [
            'q' => $query,
            'limit' => max(1, min($limit, 10)),
            'lang' => 'it',
            'lat' => self::ITALY_CENTER['lat'],
            'lon' => self::ITALY_CENTER['lng'],
        ];

        if ($country !== null && $country !== '') {
            $params['osm_tag'] = 'country:'.strtolower($country);
        }

        $response = Http::timeout(5)->acceptJson()->get(self::BASE_URL, $params);

        if (! $response->successful()) {
            return [];
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $response->json();
        $features = $payload['features'] ?? [];

        return is_array($features) ? $features : [];
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array{lat: float, lng: float, label: string, meta: array<string, mixed>}
     */
    private function mapFeature(array $feature): array
    {
        $coordinates = $feature['geometry']['coordinates'] ?? [0, 0];
        $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];

        return [
            'lat' => (float) ($coordinates[1] ?? 0),
            'lng' => (float) ($coordinates[0] ?? 0),
            'label' => $this->formatLabel($props),
            'meta' => $props,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function formatLabel(array $props): string
    {
        $parts = array_filter([
            $props['name'] ?? null,
            $props['city'] ?? null,
            $props['state'] ?? null,
            $props['country'] ?? null,
        ], fn ($part) => is_string($part) && trim($part) !== '');

        return $parts !== [] ? implode(', ', $parts) : 'Località selezionata';
    }
}
