<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Enums\AuditAction;
use App\Enums\PaymentIntentStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\PaymentIntent;
use App\Services\AuditLogService;
use App\Services\PaymentIntentService;
use App\Services\WebhookEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentIntentService $paymentIntentService,
        private readonly AuditLogService $auditLogService,
        private readonly WebhookEventService $webhookEventService,
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $eventType = 'payment.'.($payload['status'] ?? 'unknown');

        $webhookEvent = $this->webhookEventService->recordIncoming(
            $provider,
            $eventType,
            $payload,
        );

        try {
            $validated = $request->validate([
                'payment_intent_id' => ['required', 'string', 'max:64'],
                'provider_ref' => ['nullable', 'string', 'max:128'],
                'status' => ['required', 'in:completed,failed'],
            ]);

            $eventType = 'payment.'.$validated['status'];
            $webhookEvent->update(['event_type' => $eventType]);

            $intent = PaymentIntent::query()
                ->where(function ($query) use ($validated): void {
                    $id = $validated['payment_intent_id'];
                    $query->where('public_ref', $id)
                        ->orWhere('uuid', $id)
                        ->orWhere('provider_ref', $id);

                    if (is_numeric($id)) {
                        $query->orWhere('id', (int) $id);
                    }
                })
                ->first();

            if ($intent === null) {
                throw new ApiException('NOT_FOUND', 'Payment intent non trovato.', 404);
            }

            $webhookEvent->update(['payment_intent_id' => $intent->id]);

            if ($validated['status'] === 'failed') {
                $intent = $this->paymentIntentService->markFailed(
                    $intent,
                    $provider,
                    $validated['provider_ref'] ?? null,
                );

                $this->webhookEventService->markProcessed($webhookEvent, $intent);

                return ApiEnvelope::success([
                    'payment_intent' => $this->formatIntent($intent),
                ]);
            }

            $wasPending = $intent->status === PaymentIntentStatus::Pending;

            $result = $this->paymentIntentService->complete(
                $intent,
                $provider,
                $validated['provider_ref'] ?? null,
            );

            if ($wasPending && $result['transaction'] !== null) {
                $this->auditLogService->record(
                    AuditAction::WalletRecharge,
                    null,
                    $result['transaction'],
                    [
                        'company_id' => $intent->company_id,
                        'credits' => $intent->credits,
                        'amount_cents' => $intent->amount_cents,
                        'payment_method' => $intent->payment_method?->value,
                        'transaction_public_ref' => $result['transaction']->public_ref,
                        'payment_intent_id' => $intent->public_ref,
                        'provider' => $provider,
                        'provider_ref' => $validated['provider_ref'] ?? null,
                        'source' => 'webhook',
                    ],
                    $request,
                );
            }

            $this->webhookEventService->markProcessed($webhookEvent, $result['payment_intent']);

            return ApiEnvelope::success([
                'payment_intent' => $this->formatIntent($result['payment_intent']),
                'transaction' => $result['transaction'] !== null ? [
                    'id' => $result['transaction']->public_ref,
                    'status' => $result['transaction']->status->value,
                ] : null,
            ]);
        } catch (Throwable $exception) {
            $this->webhookEventService->markFailed($webhookEvent);

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatIntent(PaymentIntent $intent): array
    {
        return [
            'id' => $intent->public_ref,
            'status' => $intent->status->value,
            'amount' => $intent->amount_cents / 100,
            'credits' => $intent->credits,
        ];
    }
}
