<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class CorsPreflightTest extends TestCase
{
    public function test_b2b_onboarding_submit_preflight_allows_idempotency_key_header(): void
    {
        $response = $this->call(
            'OPTIONS',
            '/api/v1/b2b/onboarding/submit',
            server: $this->transformHeadersToServerVars([
                'HTTP_ORIGIN' => 'https://wenando.com',
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,authorization,x-xsrf-token,idempotency-key',
            ]),
        );

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', 'https://wenando.com');

        $allowed = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('idempotency-key', $allowed);
    }
}
