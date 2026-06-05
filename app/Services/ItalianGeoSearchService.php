<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ItalianLocationParser;

final class ItalianGeoSearchService
{
    private const COMUNI_PATH = 'data/italian_comuni.json.gz';

    private const LEGACY_CITIES_PATH = 'data/italian_cities.json';

    private const MAX_RESULTS = 10;

    /** @var list<array{type: string, label: string, lat: float, lng: float, meta: array<string, mixed>, normalized: string, priority: int}>|null */
    private static ?array $localIndex = null;

    public function __construct(
        private readonly PhotonGeocodingService $photon = new PhotonGeocodingService,
        private readonly ItalianLocationParser $locationParser = new ItalianLocationParser,
    ) {}

    /**
     * @return list<array{type: string, label: string, lat: float, lng: float, meta: array<string, mixed>}>
     */
    public function search(string $query, int $limit = self::MAX_RESULTS, ?string $country = 'it'): array
    {
        $normalizedQuery = $this->locationParser->normalize($query);
        $trimmedQuery = trim($query);

        if (mb_strlen($normalizedQuery) < 2 && ! $this->isCapQuery($trimmedQuery)) {
            return [];
        }

        $limit = max(1, min($limit, self::MAX_RESULTS));
        $results = [];
        $seen = [];

        foreach ($this->rankedLocalMatches($normalizedQuery, $trimmedQuery) as $entry) {
            $key = strtolower($entry['type'].'|'.$entry['label']);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $results[] = [
                'type' => $entry['type'],
                'label' => $entry['label'],
                'lat' => $entry['lat'],
                'lng' => $entry['lng'],
                'meta' => $entry['meta'],
            ];

            if (count($results) >= $limit) {
                return $results;
            }
        }

        if ($country !== null && strtolower($country) === 'it') {
            foreach ($this->photon->searchFeatures($query, $limit, $country) as $feature) {
                $mapped = $this->mapPhotonFeature($feature);

                if ($mapped === null) {
                    continue;
                }

                $key = strtolower($mapped['type'].'|'.$mapped['label']);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $results[] = $mapped;

                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * @return list<array{type: string, label: string, lat: float, lng: float, meta: array<string, mixed>}>
     */
    private function rankedLocalMatches(string $normalizedQuery, string $rawQuery): array
    {
        $matches = [];

        foreach ($this->loadLocalIndex() as $entry) {
            if (! $this->entryMatchesQuery($entry, $normalizedQuery, $rawQuery)) {
                continue;
            }

            $matches[] = $entry;
        }

        foreach ($matches as &$match) {
            $match['priority'] = $this->resolveMatchPriority($match, $normalizedQuery);
        }
        unset($match);

        usort(
            $matches,
            fn (array $a, array $b): int => $b['priority'] <=> $a['priority']
                ?: strcmp($a['normalized'], $b['normalized']),
        );

        return array_map(
            fn (array $entry): array => [
                'type' => $entry['type'],
                'label' => $entry['label'],
                'lat' => $entry['lat'],
                'lng' => $entry['lng'],
                'meta' => $entry['meta'],
            ],
            $matches,
        );
    }

    /**
     * @param  array{type: string, label: string, lat: float, lng: float, meta: array<string, mixed>, normalized: string, priority: int}  $entry
     */
    private function entryMatchesQuery(array $entry, string $normalizedQuery, string $rawQuery): bool
    {
        if ($entry['type'] === 'cap' && $this->isCapQuery($rawQuery)) {
            $cap = preg_replace('/\D/', '', $rawQuery) ?? '';

            return ($entry['meta']['cap'] ?? '') === $cap;
        }

        if ($entry['type'] === 'provincia' && preg_match('/^[a-z]{2}$/i', trim($rawQuery)) === 1) {
            $code = strtoupper(trim($rawQuery));

            return strtoupper((string) ($entry['meta']['province'] ?? '')) === $code;
        }

        return $this->matchesNormalizedQuery($entry['normalized'], $normalizedQuery);
    }

    private function isCapQuery(string $query): bool
    {
        $digits = preg_replace('/\D/', '', trim($query)) ?? '';

        return strlen($digits) >= 3 && strlen($digits) <= 5;
    }

    /**
     * @return list<array{type: string, label: string, lat: float, lng: float, meta: array<string, mixed>, normalized: string, priority: int}>
     */
    private function loadLocalIndex(): array
    {
        if (self::$localIndex !== null) {
            return self::$localIndex;
        }

        self::$localIndex = array_merge(
            $this->loadComuniIndex(),
            $this->loadProvincesIndex(),
            $this->loadRegionsIndex(),
        );

        return self::$localIndex;
    }

    /**
     * @return list<array{type: string, label: string, lat: float, lng: float, meta: array<string, mixed>, normalized: string, priority: int}>
     */
    private function loadComuniIndex(): array
    {
        $comuni = $this->readComuniDataset();
        $index = [];

        foreach ($comuni as $comune) {
            if (! is_array($comune)) {
                continue;
            }

            $name = trim((string) ($comune['name'] ?? $comune['city'] ?? ''));
            $province = strtoupper(trim((string) ($comune['province'] ?? '')));
            $region = trim((string) ($comune['region'] ?? ''));
            $cap = trim((string) ($comune['cap'] ?? ''));
            $lat = $comune['lat'] ?? null;
            $lng = $comune['lng'] ?? null;

            if ($name === '' || $province === '' || ! is_numeric($lat) || ! is_numeric($lng)) {
                continue;
            }

            $meta = [
                'city' => $name,
                'province' => $province,
                'region' => $region,
            ];

            if ($cap !== '') {
                $meta['cap'] = $cap;
            }

            $index[] = [
                'type' => 'comune',
                'label' => sprintf('%s (%s)', $name, $province),
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'meta' => $meta,
                'normalized' => $this->locationParser->normalize($name),
                'priority' => 50,
            ];

            if ($cap !== '') {
                $index[] = [
                    'type' => 'cap',
                    'label' => sprintf('%s — %s (%s)', $cap, $name, $province),
                    'lat' => (float) $lat,
                    'lng' => (float) $lng,
                    'meta' => $meta,
                    'normalized' => $cap,
                    'priority' => 90,
                ];
            }
        }

        return $index;
    }

    /**
     * @return list<array{name: string, province: string, region: string, cap: string, lat: float, lng: float}>
     */
    private function readComuniDataset(): array
    {
        $gzPath = resource_path(self::COMUNI_PATH);

        if (is_readable($gzPath)) {
            $raw = file_get_contents('compress.zlib://'.$gzPath);

            if ($raw !== false) {
                /** @var list<array<string, mixed>>|null $decoded */
                $decoded = json_decode($raw, true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $legacyPath = resource_path(self::LEGACY_CITIES_PATH);
        $raw = file_get_contents($legacyPath);

        if ($raw === false) {
            return [];
        }

        /** @var list<array<string, mixed>>|null $cities */
        $cities = json_decode($raw, true);

        if (! is_array($cities)) {
            return [];
        }

        return array_map(
            fn (array $city): array => [
                'name' => (string) ($city['city'] ?? ''),
                'province' => (string) ($city['province'] ?? ''),
                'region' => (string) ($city['region'] ?? ''),
                'cap' => '',
                'lat' => (float) ($city['lat'] ?? 0),
                'lng' => (float) ($city['lng'] ?? 0),
            ],
            array_filter($cities, 'is_array'),
        );
    }

    /**
     * @return list<array{type: string, label: string, lat: float, lng: float, meta: array<string, mixed>, normalized: string, priority: int}>
     */
    private function loadProvincesIndex(): array
    {
        $byCode = [];

        foreach ($this->readComuniDataset() as $comune) {
            $code = strtoupper(trim((string) ($comune['province'] ?? '')));

            if ($code === '' || isset($byCode[$code])) {
                continue;
            }

            $byCode[$code] = [
                'code' => $code,
                'name' => trim((string) ($comune['province_name'] ?? '')),
                'region' => trim((string) ($comune['region'] ?? '')),
                'lat' => (float) ($comune['lat'] ?? 0),
                'lng' => (float) ($comune['lng'] ?? 0),
            ];
        }

        $index = [];

        foreach ($byCode as $code => $entry) {
            $provinceName = trim((string) ($entry['name'] ?? ''));
            $label = $provinceName !== ''
                ? sprintf('Provincia di %s', $provinceName)
                : sprintf('Provincia %s', $code);
            $index[] = [
                'type' => 'provincia',
                'label' => $label.' ('.$code.')',
                'lat' => $entry['lat'],
                'lng' => $entry['lng'],
                'meta' => [
                    'province' => $code,
                    'region' => $entry['region'],
                ],
                'normalized' => $this->locationParser->normalize($label.' '.$code),
                'priority' => 70,
            ];
        }

        return $index;
    }

    /**
     * @return list<array{type: string, label: string, lat: float, lng: float, meta: array<string, mixed>, normalized: string, priority: int}>
     */
    private function loadRegionsIndex(): array
    {
        $byRegion = [];

        foreach ($this->readComuniDataset() as $comune) {
            $region = trim((string) ($comune['region'] ?? ''));

            if ($region === '' || isset($byRegion[$region])) {
                continue;
            }

            $byRegion[$region] = [
                'lat' => (float) ($comune['lat'] ?? 0),
                'lng' => (float) ($comune['lng'] ?? 0),
            ];
        }

        $index = [];

        foreach ($byRegion as $region => $entry) {
            $index[] = [
                'type' => 'regione',
                'label' => $region,
                'lat' => $entry['lat'],
                'lng' => $entry['lng'],
                'meta' => ['region' => $region],
                'normalized' => $this->locationParser->normalize($region),
                'priority' => 60,
            ];
        }

        return $index;
    }

    /**
     * @param  array{type: string, label: string, lat: float, lng: float, meta: array<string, mixed>, normalized: string, priority: int}  $entry
     */
    private function resolveMatchPriority(array $entry, string $normalizedQuery): int
    {
        $priority = $entry['priority'];

        if ($entry['type'] === 'comune') {
            if ($entry['normalized'] === $normalizedQuery) {
                return 100;
            }

            if (str_starts_with($entry['normalized'], $normalizedQuery)) {
                return max($priority, 88);
            }
        }

        if ($entry['type'] === 'cap' && str_starts_with($entry['normalized'], $normalizedQuery)) {
            return max($priority, 95);
        }

        return $priority;
    }

    private function matchesNormalizedQuery(string $normalizedEntry, string $normalizedQuery): bool
    {
        if ($normalizedQuery === '') {
            return false;
        }

        if (str_starts_with($normalizedEntry, $normalizedQuery)) {
            return true;
        }

        $tokens = preg_split('/\s+/', $normalizedQuery) ?: [];

        foreach ($tokens as $token) {
            if ($token === '' || ! str_contains($normalizedEntry, $token)) {
                return false;
            }
        }

        return $tokens !== [];
    }

    /**
     * @param  array<string, mixed>  $feature
     * @return array{type: string, label: string, lat: float, lng: float, meta: array<string, mixed>}|null
     */
    private function mapPhotonFeature(array $feature): ?array
    {
        $coordinates = $feature['geometry']['coordinates'] ?? null;

        if (! is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }

        $props = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
        $type = $this->mapPhotonType($props);
        $labelParts = array_filter([
            $props['name'] ?? null,
            $props['city'] ?? null,
            $props['state'] ?? null,
        ], fn ($part) => is_string($part) && trim($part) !== '');

        if ($labelParts === []) {
            return null;
        }

        return [
            'type' => $type,
            'label' => implode(', ', $labelParts),
            'lat' => (float) $coordinates[1],
            'lng' => (float) $coordinates[0],
            'meta' => $props,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function mapPhotonType(array $props): string
    {
        $osmValue = strtolower((string) ($props['osm_value'] ?? ''));

        return match (true) {
            str_contains($osmValue, 'postcode') => 'cap',
            str_contains($osmValue, 'state') => 'regione',
            str_contains($osmValue, 'county') => 'provincia',
            default => 'comune',
        };
    }
}
