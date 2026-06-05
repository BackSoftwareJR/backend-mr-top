<?php

declare(strict_types=1);

namespace Tests\Feature\Geo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class GeoSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('geo-search');
    }

    public function test_geo_search_returns_local_comune_results(): void
    {
        $response = $this->getJson('/api/v1/geo/search?q=milano&limit=5');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['results']]);

        $results = $response->json('data.results');
        $this->assertNotEmpty($results);
        $this->assertSame('comune', $results[0]['type']);
        $this->assertArrayHasKey('lat', $results[0]);
        $this->assertArrayHasKey('lng', $results[0]);
    }

    public function test_geo_search_includes_province_and_region_matches(): void
    {
        $province = $this->getJson('/api/v1/geo/search?q=provincia%20milano')->json('data.results');
        $region = $this->getJson('/api/v1/geo/search?q=lombardia')->json('data.results');

        $this->assertTrue(collect($province)->contains(fn (array $item): bool => $item['type'] === 'provincia'));
        $this->assertTrue(collect($region)->contains(fn (array $item): bool => $item['type'] === 'regione'));
    }

    public function test_geo_search_finds_comune_by_name(): void
    {
        $results = $this->getJson('/api/v1/geo/search?q=bergamo&limit=5')->json('data.results');

        $this->assertNotEmpty($results);
        $this->assertTrue(
            collect($results)->contains(
                fn (array $item): bool => $item['type'] === 'comune'
                    && str_contains(strtolower($item['label']), 'bergamo'),
            ),
        );
    }

    public function test_geo_search_finds_cap_code(): void
    {
        $results = $this->getJson('/api/v1/geo/search?q=20121&limit=5')->json('data.results');

        $this->assertNotEmpty($results);
        $this->assertTrue(collect($results)->contains(fn (array $item): bool => $item['type'] === 'cap'));
        $this->assertStringContainsString('20121', $results[0]['label']);
    }

    public function test_geo_search_finds_province_by_name(): void
    {
        $results = $this->getJson('/api/v1/geo/search?q=provincia%20bergamo&limit=5')->json('data.results');

        $this->assertTrue(
            collect($results)->contains(
                fn (array $item): bool => $item['type'] === 'provincia'
                    && ($item['meta']['province'] ?? '') === 'BG',
            ),
        );
    }

    public function test_geo_reverse_returns_label_from_photon(): void
    {
        Http::fake([
            'photon.komoot.io/*' => Http::response([
                'features' => [[
                    'geometry' => ['coordinates' => [9.19, 45.4642]],
                    'properties' => [
                        'name' => 'Milano',
                        'city' => 'Milano',
                        'state' => 'Lombardia',
                        'country' => 'Italia',
                    ],
                ]],
            ]),
        ]);

        $this->getJson('/api/v1/geo/reverse?lat=45.4642&lng=9.19')
            ->assertOk()
            ->assertJsonPath('data.label', 'Milano, Milano, Lombardia, Italia');
    }

    public function test_geo_reverse_validates_coordinates(): void
    {
        $this->getJson('/api/v1/geo/reverse?lat=abc&lng=9')
            ->assertStatus(422);
    }
}
