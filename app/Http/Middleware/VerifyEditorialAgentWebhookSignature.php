<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyEditorialAgentWebhookSignature
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('editorial.agent_webhook_secret');

        if (! is_string($secret) || $secret === '') {
            throw new ApiException(
                'WEBHOOK_NOT_CONFIGURED',
                'Webhook agent editorial non configurato.',
                503,
            );
        }

        $provided = $request->header('X-Editorial-Agent-Signature');

        if (! is_string($provided) || $provided === '') {
            throw new ApiException(
                'WEBHOOK_UNAUTHORIZED',
                'Firma webhook non valida.',
                401,
            );
        }

        if (str_starts_with($provided, 'sha256=')) {
            $provided = substr($provided, 7);
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $provided)) {
            throw new ApiException(
                'WEBHOOK_UNAUTHORIZED',
                'Firma webhook non valida.',
                401,
            );
        }

        return $next($request);
    }
}
