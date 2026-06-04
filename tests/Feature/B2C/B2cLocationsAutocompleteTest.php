<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2cLocationsAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_locations_autocomplete_returns_matching_cities_by_prefix(): void
    {
        $response = $this->getJson('/api/v1/b2c/locations/autocomplete?q=mil');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'suggestions' => [
                        '*' => ['label', 'city', 'province', 'region'],
                    ],
                ],
            ]);

        $labels = array_column($response->json('data.suggestions'), 'label');

        $this->assertContains('Milano (MI)', $labels);
        $this->assertContains('Milazzo (ME)', $labels);
    }

    public function test_locations_autocomplete_returns_empty_for_short_query(): void
    {
        $response = $this->getJson('/api/v1/b2c/locations/autocomplete?q=m');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.suggestions', []);
    }

    public function test_locations_autocomplete_returns_empty_for_unknown_prefix(): void
    {
        $response = $this->getJson('/api/v1/b2c/locations/autocomplete?q=zzzz');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.suggestions', []);
    }

    public function test_locations_autocomplete_is_case_insensitive(): void
    {
        $response = $this->getJson('/api/v1/b2c/locations/autocomplete?q=ROM');

        $response->assertOk();

        $labels = array_column($response->json('data.suggestions'), 'label');

        $this->assertContains('Roma (RM)', $labels);
    }
}
