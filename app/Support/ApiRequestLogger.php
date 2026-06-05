<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ApiRequestLogger
{
    public const CHANNEL = CentralLog::JSON_CHANNEL;

    public static function logRequest(Request $request, Response $response, int $durationMs): void
    {
        $status = $response->getStatusCode();
        $level = self::levelForStatus($status);

        CentralLog::api('api', self::payload($request, $status, $durationMs), $level);
    }

    public static function logException(Request $request, Throwable $exception): void
    {
        $status = self::exceptionStatus($exception);
        if ($status < 500) {
            return;
        }

        $payload = self::payload($request, $status, 0);
        $payload['event'] = 'exception';
        $payload['exception'] = $exception::class;

        CentralLog::api('api.exception', $payload, 'error', $exception);
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(Request $request, int $status, int $durationMs): array
    {
        $route = $request->route();
        $routeName = $route?->getName();
        $routeLabel = is_string($routeName) && $routeName !== ''
            ? $routeName
            : $request->path();

        return [
            'level' => self::levelForStatus($status),
            'request_id' => $request->attributes->get('request_id'),
            'route' => $routeLabel,
            'user_id' => Auth::id(),
            'status' => $status,
            'duration_ms' => $durationMs,
        ];
    }

    private static function levelForStatus(int $status): string
    {
        if ($status >= 500) {
            return 'error';
        }

        if ($status >= 400) {
            return 'warning';
        }

        return 'info';
    }

    private static function exceptionStatus(Throwable $exception): int
    {
        if (method_exists($exception, 'getStatusCode')) {
            return (int) $exception->getStatusCode();
        }

        return 500;
    }
}
