<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhooksController extends Controller
{
    public function events(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->integer('per_page', 50)));

        $paginator = WebhookEvent::query()
            ->with('paymentIntent:id,public_ref')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $events = collect($paginator->items())->map(fn (WebhookEvent $event): array => [
            'id' => $event->id,
            'provider' => $event->provider,
            'event_type' => $event->event_type,
            'status' => $event->status->value,
            'payment_intent_id' => $event->paymentIntent?->public_ref,
            'payload' => $event->payload,
            'created_at' => $event->created_at?->toIso8601String(),
        ])->all();

        return ApiEnvelope::success(
            ['events' => $events],
            200,
            [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
