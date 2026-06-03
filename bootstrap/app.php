<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Hostinger reverse proxy: trust X-Forwarded-* for HTTPS detection and client IP.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        $middleware->statefulApi();

        $middleware->api(prepend: [
            ThrottleRequests::class.':api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $apiRender = static function (
            Request $request,
            string $code,
            string $message,
            int $status,
            ?array $details = null,
        ) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make($code, $message, $status, $details);
        };

        $exceptions->render(function (ApiException $e, Request $request) use ($apiRender) {
            return $apiRender(
                $request,
                $e->errorCode,
                $e->getMessage(),
                $e->status,
                $e->details,
            );
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($apiRender) {
            return $apiRender(
                $request,
                'VALIDATION_FAILED',
                'I dati inviati non sono validi.',
                422,
                $e->errors(),
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($apiRender) {
            return $apiRender(
                $request,
                'UNAUTHENTICATED',
                'Autenticazione richiesta.',
                401,
            );
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($apiRender) {
            return $apiRender(
                $request,
                'FORBIDDEN',
                'Non hai i permessi per accedere a questa risorsa.',
                403,
            );
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($apiRender) {
            return $apiRender(
                $request,
                'NOT_FOUND',
                'Risorsa non trovata.',
                404,
            );
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($apiRender) {
            return $apiRender(
                $request,
                'NOT_FOUND',
                'Endpoint non trovato.',
                404,
            );
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) use ($apiRender) {
            $response = $apiRender(
                $request,
                'RATE_LIMITED',
                'Troppe richieste. Riprova tra qualche minuto.',
                429,
            );

            if ($response !== null && $e->getHeaders()['Retry-After'] ?? false) {
                $response->headers->set('Retry-After', (string) $e->getHeaders()['Retry-After']);
            }

            return $response;
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($apiRender) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface && ! $e instanceof TooManyRequestsHttpException) {
                return $apiRender(
                    $request,
                    'HTTP_ERROR',
                    $e->getMessage() ?: 'Richiesta non valida.',
                    $e->getStatusCode(),
                );
            }

            report($e);

            return $apiRender(
                $request,
                'SERVER_ERROR',
                'Si è verificato un errore. Riprova più tardi.',
                500,
            );
        });
    })->create();
