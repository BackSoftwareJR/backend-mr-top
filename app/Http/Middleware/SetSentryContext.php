<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

use function Sentry\configureScope;

/**
 * Attach API request_id to Sentry scope when error tracking is enabled.
 */
final class SetSentryContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('sentry.dsn')) {
            $requestId = $request->attributes->get('request_id');

            if (is_string($requestId) && $requestId !== '') {
                configureScope(static function (Scope $scope) use ($requestId): void {
                    $scope->setTag('request_id', $requestId);
                });
            }
        }

        return $next($request);
    }
}
