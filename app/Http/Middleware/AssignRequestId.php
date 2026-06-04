<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);
        $request->attributes->set('request_id', $requestId);
        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $header = $request->header('X-Request-Id');

        if (is_string($header) && $header !== '' && $this->isValidRequestId($header)) {
            return $header;
        }

        return (string) Str::ulid();
    }

    private function isValidRequestId(string $id): bool
    {
        return strlen($id) <= 128 && preg_match('/^[\w\-.:]+$/', $id) === 1;
    }
}
