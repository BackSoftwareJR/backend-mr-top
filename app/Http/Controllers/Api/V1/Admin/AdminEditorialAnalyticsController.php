<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\EditorialContent;
use App\Services\Editorial\EditorialAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEditorialAnalyticsController extends Controller
{
    public function __construct(
        private readonly EditorialAnalyticsService $analyticsService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('accessAdmin', EditorialContent::class);

        [$from, $to] = $this->analyticsService->resolveDateRange(
            $request->query('from'),
            $request->query('to'),
        );

        return ApiEnvelope::success(
            $this->analyticsService->getPlatformOverview($from, $to),
        );
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        $content = EditorialContent::query()->where('uuid', $uuid)->firstOrFail();

        $this->authorize('view', $content);

        [$from, $to] = $this->analyticsService->resolveDateRange(
            $request->query('from'),
            $request->query('to'),
        );

        $stats = $this->analyticsService->getContentStats($content->id, $from, $to);
        $stats['content'] = [
            'uuid' => $content->uuid,
            'title' => $content->title,
            'slug' => $content->slug,
            'rubric_slug' => $content->rubric_slug,
        ];

        return ApiEnvelope::success($stats);
    }
}
