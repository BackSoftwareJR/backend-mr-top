<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestIdCorrelationTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_REQUEST_ID = '01JTESTCLIENTREQUESTID00';

    public function test_success_response_echoes_client_request_id(): void
    {
        $response = $this->getJson('/api/v1/health', [
            'X-Request-Id' => self::CLIENT_REQUEST_ID,
        ]);

        $response->assertOk()
            ->assertJsonPath('meta.request_id', self::CLIENT_REQUEST_ID)
            ->assertHeader('X-Request-Id', self::CLIENT_REQUEST_ID);
    }

    public function test_error_response_uses_same_request_id_as_client_header(): void
    {
        $response = $this->postJson('/api/v1/b2c/leads', [], [
            'X-Request-Id' => self::CLIENT_REQUEST_ID,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('trace_id', self::CLIENT_REQUEST_ID)
            ->assertJsonPath('request_id', self::CLIENT_REQUEST_ID)
            ->assertHeader('X-Request-Id', self::CLIENT_REQUEST_ID);
    }

    public function test_success_response_generates_request_id_when_header_missing(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $requestId = $response->json('meta.request_id');
        $this->assertIsString($requestId);
        $this->assertNotEmpty($requestId);
        $response->assertHeader('X-Request-Id', $requestId);
    }
}
