<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\IdempotencyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleIdempotency
{
    public function __construct(
        private readonly IdempotencyService $idempotencyService,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $action, string $ttl = '1440'): Response
    {
        return $this->idempotencyService->execute(
            $request,
            $action,
            (int) $ttl,
            fn (): Response => $next($request),
        );
    }
}
