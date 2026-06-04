<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ApiRequestLogger
{
    public const CHANNEL = 'json';

    public static function logRequest(Request $request, Response $response, int $durationMs): void
    {
        self::write(self::payload($request, $response->getStatusCode(), $durationMs));
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

        self::write($payload, 'error');
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function write(array $payload, ?string $level = null): void
    {
        $level ??= is_string($payload['level'] ?? null) ? $payload['level'] : 'info';

        Log::channel(self::CHANNEL)->log(
            $level,
            'api',
            $payload,
        );
    }
}
