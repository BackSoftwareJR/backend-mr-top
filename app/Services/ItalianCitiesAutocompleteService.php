<?php

declare(strict_types=1);

namespace App\Services;

final class ItalianCitiesAutocompleteService
{
    private const DATA_PATH = 'data/italian_cities.json';

    private const MAX_RESULTS = 10;

    /** @var list<array{city: string, province: string, region: string, label: string, normalized: string}>|null */
    private static ?array $index = null;

    /**
     * @return list<array{label: string, city: string, province: string, region: string}>
     */
    public function search(string $query): array
    {
        $normalizedQuery = $this->normalize($query);

        if (mb_strlen($normalizedQuery) < 2) {
            return [];
        }

        $matches = [];

        foreach ($this->loadIndex() as $entry) {
            if (! str_starts_with($entry['normalized'], $normalizedQuery)) {
                continue;
            }

            $matches[] = [
                'label' => $entry['label'],
                'city' => $entry['city'],
                'province' => $entry['province'],
                'region' => $entry['region'],
            ];

            if (count($matches) >= self::MAX_RESULTS) {
                break;
            }
        }

        return $matches;
    }

    /**
     * @return list<array{city: string, province: string, region: string, label: string, normalized: string}>
     */
    private function loadIndex(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        $path = resource_path(self::DATA_PATH);
        $raw = file_get_contents($path);

        if ($raw === false) {
            self::$index = [];

            return self::$index;
        }

        /** @var list<array{city: string, province: string, region: string}>|null $cities */
        $cities = json_decode($raw, true);
        self::$index = [];

        if (! is_array($cities)) {
            return self::$index;
        }

        foreach ($cities as $city) {
            if (! is_array($city)) {
                continue;
            }

            $name = trim((string) ($city['city'] ?? ''));
            $province = strtoupper(trim((string) ($city['province'] ?? '')));
            $region = trim((string) ($city['region'] ?? ''));

            if ($name === '' || $province === '') {
                continue;
            }

            self::$index[] = [
                'city' => $name,
                'province' => $province,
                'region' => $region,
                'label' => sprintf('%s (%s)', $name, $province),
                'normalized' => $this->normalize($name),
            ];
        }

        usort(
            self::$index,
            fn (array $a, array $b): int => strcmp($a['normalized'], $b['normalized']),
        );

        return self::$index;
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        if (function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);

            if (is_string($transliterated) && $transliterated !== '') {
                $text = $transliterated;
            }
        }

        return $text;
    }
}
