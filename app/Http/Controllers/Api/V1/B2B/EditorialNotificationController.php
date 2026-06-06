<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Enums\EditorialNotificationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\EditorialContent;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditorialNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EditorialContent::class);

        $company = $this->resolveCompany($request->user());

        $notifications = Notification::query()
            ->where('notifiable_type', $company::class)
            ->where('notifiable_id', $company->id)
            ->where('type', EditorialNotificationType::ReviewOutcome->value)
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
        $this->authorize('viewAny', EditorialContent::class);

        $company = $this->resolveCompany($request->user());
        $notification = Notification::query()
            ->where('notifiable_type', $company::class)
            ->where('notifiable_id', $company->id)
            ->where('type', EditorialNotificationType::ReviewOutcome->value)
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

    private function resolveCompany(User $user)
    {
        $company = $user->companies()->first();

        if ($company === null) {
            abort(403, 'Nessuna struttura associata all\'utente.');
        }

        return $company;
    }
}
