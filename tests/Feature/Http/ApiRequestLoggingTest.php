<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Support\ApiRequestLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiRequestLoggingTest extends TestCase
{
    public function test_api_request_logger_emits_structured_payload(): void
    {
        $request = Request::create('/api/v1/health', 'GET');
        $request->attributes->set('request_id', '01JTESTLOGGINGREQUESTID0');

        Log::shouldReceive('channel')
            ->once()
            ->with(ApiRequestLogger::CHANNEL)
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context): bool {
                return $level === 'info'
                    && $message === 'api'
                    && $context['request_id'] === '01JTESTLOGGINGREQUESTID0'
                    && $context['route'] === 'api/v1/health'
                    && $context['user_id'] === null
                    && $context['status'] === 200
                    && $context['duration_ms'] === 42;
            });

        ApiRequestLogger::logRequest($request, new Response('', 200), 42);
    }

    public function test_api_request_logger_maps_validation_status_to_warning(): void
    {
        $request = Request::create('/api/v1/b2c/leads', 'POST');

        Log::shouldReceive('channel')
            ->once()
            ->with(ApiRequestLogger::CHANNEL)
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context): bool {
                return $level === 'warning'
                    && $message === 'api'
                    && $context['status'] === 422
                    && $context['level'] === 'warning';
            });

        ApiRequestLogger::logRequest($request, new Response('', 422), 3);
    }
}
