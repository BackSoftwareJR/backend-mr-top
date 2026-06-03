<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\Notification;
use App\Services\B2bDashboardService;
use App\Services\B2bOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly B2bDashboardService $dashboardService,
        private readonly B2bOnboardingService $onboardingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());

        return ApiEnvelope::success($this->dashboardService->dashboard($company));
    }

    public function notifications(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());
        $notifications = Notification::query()
            ->where('notifiable_type', $company::class)
            ->where('notifiable_id', $company->id)
            ->latest()
            ->limit(50)
            ->get();

        $unread = $notifications->whereNull('read_at')->count();

        return ApiEnvelope::success([
            'notifications' => $notifications->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
            ])->values()->all(),
            'unread_count' => $unread,
        ]);
    }

    public function markNotificationRead(Request $request, string $id): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());
        $notification = Notification::query()
            ->where('notifiable_type', $company::class)
            ->where('notifiable_id', $company->id)
            ->whereKey($id)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return ApiEnvelope::success(['notification' => ['id' => $notification->id, 'read_at' => $notification->read_at?->toIso8601String()]]);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());
        Notification::query()
            ->where('notifiable_type', $company::class)
            ->where('notifiable_id', $company->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiEnvelope::success(['success' => true]);
    }
}
