<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Str;

/**
 * Standard API success envelope: { success, data, meta }.
 *
 * @see backend/docs/6_API_CONTRACTS_&_PAYLOADS.md §1
 */
final class ApiEnvelope
{
    /**
     * @param  mixed  $data  JsonResource, ResourceCollection, array, or scalar
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        $resolved = match (true) {
            $data instanceof JsonResource,
            $data instanceof ResourceCollection => $data->resolve(),
            default => $data,
        };

        return response()->json([
            'success' => true,
            'data' => $resolved,
            'meta' => array_merge(
                ['request_id' => (string) (request()->header('X-Request-Id') ?? Str::ulid())],
                $meta,
            ),
        ], $status);
    }
}
