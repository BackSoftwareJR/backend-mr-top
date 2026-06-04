<?php

declare(strict_types=1);

namespace Tests\Feature\B2C;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class B2cLeadResultsRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_REQUEST_ID = '01JTESTB2CLEADRESULTSRATELIMIT';

    private string $leadUuid;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('b2c-lead-results');

        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->leadUuid = (string) Str::uuid();

        Lead::query()->create([
            'uuid' => $this->leadUuid,
            'sector_id' => $sector->id,
            'status' => LeadStatus::Routed,
            'payload' => ['autonomy' => 'parziale'],
            'location_label' => 'Milano (MI)',
        ]);
    }

    public function test_lead_results_returns_rate_limited_on_61st_request_in_window(): void
    {
        $url = "/api/v1/b2c/leads/{$this->leadUuid}/results";
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
