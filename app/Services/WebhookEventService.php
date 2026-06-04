<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WebhookEventStatus;
use App\Models\PaymentIntent;
use App\Models\WebhookEvent;

class WebhookEventService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordIncoming(
        string $provider,
        string $eventType,
        array $payload,
        ?PaymentIntent $paymentIntent = null,
    ): WebhookEvent {
        return WebhookEvent::query()->create([
            'provider' => $provider,
            'event_type' => $eventType,
            'payload' => $payload,
            'payment_intent_id' => $paymentIntent?->id,
            'status' => WebhookEventStatus::Failed,
        ]);
    }

    public function markProcessed(WebhookEvent $event, ?PaymentIntent $paymentIntent = null): WebhookEvent
    {
        $attributes = ['status' => WebhookEventStatus::Processed];

        if ($paymentIntent !== null) {
            $attributes['payment_intent_id'] = $paymentIntent->id;
        }

        $event->update($attributes);

        return $event->refresh();
    }

    public function markFailed(WebhookEvent $event): WebhookEvent
    {
        $event->update(['status' => WebhookEventStatus::Failed]);

        return $event->refresh();
    }
}
