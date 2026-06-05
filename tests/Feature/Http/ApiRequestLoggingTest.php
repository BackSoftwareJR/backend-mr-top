<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Enums\AppLogChannel;
use App\Models\AppLog;
use App\Support\ApiRequestLogger;
use App\Support\CentralLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiRequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_request_logger_emits_structured_payload(): void
    {
        $request = Request::create('/api/v1/health', 'GET');
        $request->attributes->set('request_id', '01JTESTLOGGINGREQUESTID0');

        Log::shouldReceive('channel')
            ->once()
            ->with(CentralLog::JSON_CHANNEL)
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
        $request->attributes->set('request_id', '01JTESTWARNINGREQUESTID0');

        Log::shouldReceive('channel')
            ->once()
            ->with(CentralLog::JSON_CHANNEL)
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

        $this->assertDatabaseHas('app_logs', [
            'request_id' => '01JTESTWARNINGREQUESTID0',
            'channel' => AppLogChannel::Api->value,
            'level' => 'warning',
            'message' => 'api',
        ]);
    }

    public function test_500_error_creates_app_log_row_with_request_id(): void
    {
        Route::get('/api/v1/_test/internal-error', function (): never {
            throw new \RuntimeException('Simulated server error');
        });

        $response = $this->getJson('/api/v1/_test/internal-error')
            ->assertStatus(500)
            ->assertJsonPath('error.code', 'SERVER_ERROR');

        $requestId = $response->json('meta.request_id');
        $this->assertIsString($requestId);
        $this->assertNotSame('', $requestId);

        $this->assertDatabaseHas('app_logs', [
            'request_id' => $requestId,
            'channel' => AppLogChannel::Api->value,
            'level' => 'error',
            'message' => 'api.exception',
        ]);

        $log = AppLog::query()->where('request_id', $requestId)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('RuntimeException', (string) $log->exception);
    }
}
