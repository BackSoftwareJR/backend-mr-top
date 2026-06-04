<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class B2cLocationsAutocompleteRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_REQUEST_ID = '01JTESTB2CLOCATIONSACRATELIMIT';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('locations-autocomplete');
    }

    public function test_locations_autocomplete_returns_rate_limited_on_61st_request_in_window(): void
    {
        $url = '/api/v1/b2c/locations/autocomplete?q=mil';
        $headers = ['X-Request-Id' => self::CLIENT_REQUEST_ID];

        for ($i = 0; $i < 60; $i++) {
            $this->assertNotSame(429, $this->getJson($url, $headers)->status());
        }

        $response = $this->getJson($url, $headers);

        $response->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'RATE_LIMITED')
            ->assertJsonPath('trace_id', self::CLIENT_REQUEST_ID)
            ->assertJsonPath('request_id', self::CLIENT_REQUEST_ID)
            ->assertHeader('X-Request-Id', self::CLIENT_REQUEST_ID);

        $retryAfter = $response->headers->get('Retry-After');
        $this->assertNotNull($retryAfter);
        $this->assertGreaterThan(0, (int) $retryAfter);
    }
}
