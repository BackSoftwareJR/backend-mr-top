<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Http\Resources\Concerns\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

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
        $requestId = ApiEnvelope::requestId();
        Log::withContext(['request_id' => $requestId]);

        $payload = [
            'success' => false,
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ], fn ($value) => $value !== null),
            'trace_id' => $requestId,
            'request_id' => $requestId,
        ];

        return response()->json($payload, $status);
    }
}
