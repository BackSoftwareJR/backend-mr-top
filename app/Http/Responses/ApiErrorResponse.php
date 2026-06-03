<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

final class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public static function make(
        string $code,
        string $message,
        int $status,
        ?array $details = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ], fn ($value) => $value !== null),
            'trace_id' => (string) Str::ulid(),
        ];

        return response()->json($payload, $status);
    }
}
