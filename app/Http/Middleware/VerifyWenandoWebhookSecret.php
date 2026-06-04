<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWenandoWebhookSecret
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('wenando.webhook_secret');

        if (! is_string($expected) || $expected === '') {
            throw new ApiException(
                'WEBHOOK_NOT_CONFIGURED',
                'Webhook non configurato.',
                503,
            );
        }

        $provided = $request->header('X-Wenando-Webhook-Secret');

        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            throw new ApiException(
                'WEBHOOK_UNAUTHORIZED',
                'Firma webhook non valida.',
                401,
            );
        }

        return $next($request);
    }
}
