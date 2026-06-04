<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class IdempotencyService
{
    /**
     * @param  callable(): SymfonyResponse  $process
     */
    public function execute(
        Request $request,
        string $action,
        int $ttlMinutes,
        callable $process,
    ): SymfonyResponse {
        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '') {
            return $process();
        }

        if (! Str::isUuid($key)) {
            throw new ApiException(
                'VALIDATION_FAILED',
                'Idempotency-Key deve essere un UUID valido.',
                422,
                ['idempotency_key' => ['Il formato UUID non è valido.']],
            );
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $process();
        }

        $requestHash = $this->hashRequest($request);
        $lockKey = sprintf('idempotency:%s:%d:%s', $key, $user->id, $action);

        return Cache::lock($lockKey, 30)->block(10, function () use (

            $key,
            $user,
            $action,
            $ttlMinutes,
            $requestHash,
            $process,
        ): SymfonyResponse {
            $existing = IdempotencyKey::query()
                ->where('idempotency_key', $key)
                ->where('user_id', $user->id)
                ->where('action', $action)
                ->first();

            if ($existing !== null && ! $existing->isExpired()) {
                if ($existing->request_hash !== $requestHash) {
                    throw new ApiException(
                        'IDEMPOTENCY_KEY_MISMATCH',
                        'La richiesta non corrisponde alla chiave di idempotenza già utilizzata.',
                        422,
                    );
                }

                if ($existing->hasStoredResponse()) {
                    return response()->json(
                        $existing->response_body,
                        $existing->response_status,
                    );
                }
            }

            if ($existing !== null && $existing->isExpired()) {
                $existing->delete();
                $existing = null;
            }

            $record = $existing ?? IdempotencyKey::query()->create([
                'idempotency_key' => $key,
                'user_id' => $user->id,
                'company_id' => $this->resolveCompanyId($user),
                'action' => $action,
                'request_hash' => $requestHash,
                'expires_at' => now()->addMinutes($ttlMinutes),
            ]);

            $response = $process();

            if ($this->shouldStoreResponse($response)) {
                $decoded = json_decode($response->getContent(), true);

                if (is_array($decoded)) {
                    $record->update([
                        'response_status' => $response->getStatusCode(),
                        'response_body' => $decoded,
                    ]);
                }
            } else {
                $record->delete();
            }

            return $response;
        });
    }

    public function hashRequest(Request $request): string
    {
        $routeParams = [];

        if ($request->route() !== null) {
            foreach ($request->route()->parameters() as $name => $value) {
                if (is_object($value) && method_exists($value, 'getRouteKey')) {
                    $routeParams[$name] = $value->getRouteKey();
                } else {
                    $routeParams[$name] = $value;
                }
            }
        }

        ksort($routeParams);

        $payload = [
            'method' => $request->method(),
            'path' => $request->path(),
            'route_params' => $routeParams,
            'body' => $request->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function resolveCompanyId(User $user): ?int
    {
        return $user->companies()->value('companies.id');
    }

    private function shouldStoreResponse(SymfonyResponse $response): bool
    {
        $status = $response->getStatusCode();

        return $status >= 200 && $status < 500;
    }
}
