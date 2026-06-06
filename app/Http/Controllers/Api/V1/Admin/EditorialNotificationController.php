<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\EditorialNotificationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\EditorialContent;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditorialNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('accessAdmin', EditorialContent::class);

        $user = $request->user();
        $types = array_map(
            static fn (EditorialNotificationType $type): string => $type->value,
            EditorialNotificationType::cases(),
        );

        $notifications = Notification::query()
            ->where('notifiable_type', $user::class)
            ->where('notifiable_id', $user->id)
            ->whereIn('type', $types)
            ->latest()
            ->limit(50)
            ->get();

        $unreadCount = $notifications->whereNull('read_at')->count();

        return ApiEnvelope::success([
            'notifications' => $notifications->map(static fn (Notification $notification): array => [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ])->values()->all(),
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $this->authorize('accessAdmin', EditorialContent::class);

        $user = $request->user();
        $notification = Notification::query()
            ->where('notifiable_type', $user::class)
            ->where('notifiable_id', $user->id)
            ->whereKey($id)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return ApiEnvelope::success([
            'notification' => [
                'id' => $notification->id,
                'read_at' => $notification->read_at?->toIso8601String(),
            ],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->authorize('accessAdmin', EditorialContent::class);

        $user = $request->user();
        $types = array_map(
            static fn (EditorialNotificationType $type): string => $type->value,
            EditorialNotificationType::cases(),
        );

        Notification::query()
            ->where('notifiable_type', $user::class)
            ->where('notifiable_id', $user->id)
            ->whereIn('type', $types)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiEnvelope::success(['success' => true]);
    }
}
