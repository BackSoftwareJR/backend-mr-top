<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $dbOk = false;
        $queueOk = false;

        try {
            DB::connection()->getPdo();
            $dbOk = true;
            $queueOk = Schema::hasTable('jobs');
        } catch (\Throwable) {
            $dbOk = false;
        }

        $status = $dbOk && $queueOk ? 'ok' : 'degraded';

        return ApiEnvelope::success([
            'status' => $status,
            'db' => $dbOk,
            'queue' => $queueOk,
        ]);
    }
}
