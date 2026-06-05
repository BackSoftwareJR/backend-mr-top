<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Enums\AuditAction;
use App\Enums\PaymentIntentStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\PaymentIntent;
use App\Models\WebhookEvent;
use App\Services\AuditLogService;
use App\Services\PaymentIntentService;
use App\Services\WebhookEventService;
use App\Support\CentralLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent as StripePaymentIntent;
use Stripe\Webhook;
use Throwable;

class StripePaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentIntentService $paymentIntentService,
        private readonly AuditLogService $auditLogService,
        private readonly WebhookEventService $webhookEventService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            throw new ApiException(
                'WEBHOOK_NOT_CONFIGURED',
                'Webhook Stripe non configurato.',
                503,
            );
        }

        $rawPayload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (! is_string($signature) || $signature === '') {
            throw new ApiException(
                'WEBHOOK_UNAUTHORIZED',
                'Firma webhook non valida.',
                401,
            );
        }

        try {
            $event = Webhook::constructEvent($rawPayload, $signature, $secret);
        } catch (SignatureVerificationException) {
            throw new ApiException(
                'WEBHOOK_UNAUTHORIZED',
                'Firma webhook non valida.',
                401,
            );
        } catch (\UnexpectedValueException) {
            throw new ApiException(
                'WEBHOOK_INVALID',
                'Payload webhook non valido.',
                400,
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);

        $webhookEvent = $this->webhookEventService->recordIncoming(
            'stripe',
            $event->type,
            $payload,
        );

        try {
            if ($event->type === 'payment_intent.succeeded') {
                $stripeIntent = $event->data->object;

                if ($stripeIntent instanceof StripePaymentIntent) {
                    $this->handlePaymentIntentSucceeded($stripeIntent, $request, $webhookEvent);
                }
            }

            $this->webhookEventService->markProcessed($webhookEvent);

            return ApiEnvelope::success(['received' => true]);
        } catch (Throwable $exception) {
            $this->webhookEventService->markFailed($webhookEvent);

            CentralLog::webhook(
                'webhook.processing_failed',
                [
                    'provider' => 'stripe',
                    'event_type' => $event->type,
                    'webhook_event_id' => $webhookEvent->id,
                ],
                'error',
                $exception,
            );

            throw $exception;
        }
    }

    private function handlePaymentIntentSucceeded(
        StripePaymentIntent $stripeIntent,
        Request $request,
        WebhookEvent $webhookEvent,
    ): void {
        $intent = $this->resolveInternalIntent($stripeIntent);

        if ($intent === null) {
            throw new ApiException('NOT_FOUND', 'Payment intent non trovato.', 404);
        }

        $webhookEvent->update(['payment_intent_id' => $intent->id]);

        $wasPending = $intent->status === PaymentIntentStatus::Pending;

        $result = $this->paymentIntentService->complete(
            $intent,
            'stripe',
            $stripeIntent->id,
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
                    'provider' => 'stripe',
                    'provider_ref' => $stripeIntent->id,
                    'source' => 'stripe_webhook',
                ],
                $request,
            );
        }
    }

    private function resolveInternalIntent(StripePaymentIntent $stripeIntent): ?PaymentIntent
    {
        $metadata = $stripeIntent->metadata->toArray();
        $publicRef = $metadata['wenando_payment_intent_ref'] ?? null;

        if (is_string($publicRef) && $publicRef !== '') {
            $intent = PaymentIntent::query()->where('public_ref', $publicRef)->first();

            if ($intent !== null) {
                return $intent;
            }
        }

        return PaymentIntent::query()
            ->where('provider_ref', $stripeIntent->id)
            ->first();
    }
}
