<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\AppLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemLogsController extends Controller
{
    private const RETENTION_DAYS = 7;

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 50)));
        $since = now()->subDays(self::RETENTION_DAYS);

        $query = AppLog::query()
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('level')) {
            $query->where('level', $request->string('level')->toString());
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel')->toString());
        }

        if ($request->filled('request_id')) {
            $query->where('request_id', $request->string('request_id')->toString());
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->integer('company_id'));
        }

        $paginator = $query->paginate($perPage);

        $logs = collect($paginator->items())->map(fn (AppLog $log): array => [
            'id' => $log->id,
            'request_id' => $log->request_id,
            'user_id' => $log->user_id,
            'company_id' => $log->company_id,
            'channel' => $log->channel->value,
            'level' => $log->level,
            'message' => $log->message,
            'context' => $log->context,
            'exception' => $log->exception,
            'created_at' => $log->created_at?->toIso8601String(),
        ])->all();

        return ApiEnvelope::success(
            ['logs' => $logs],
            200,
            [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'retention_days' => self::RETENTION_DAYS,
            ],
        );
    }
}
