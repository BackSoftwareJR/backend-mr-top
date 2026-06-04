<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiRequestLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        $response = $next($request);

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        ApiRequestLogger::logRequest($request, $response, $durationMs);

        return $response;
    }
}
