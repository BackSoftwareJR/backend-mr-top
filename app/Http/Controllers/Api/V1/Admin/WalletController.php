<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AuditAction;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\WalletResource;
use App\Models\PaymentIntent;
use App\Services\AuditLogService;
use App\Services\PaymentIntentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private readonly PaymentIntentService $paymentIntentService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function pendingTransfers(): JsonResponse
    {
        $intents = PaymentIntent::query()
            ->where('status', PaymentIntentStatus::Pending)
            ->where(function ($query): void {
                $query->where('payment_method', PaymentMethod::Transfer)
                    ->orWhere('payment_method', 'bank_transfer');
            })
            ->with('company:id,organization_name,legal_name')
            ->orderByDesc('created_at')
            ->get();

        return ApiEnvelope::success([
            'pending_transfers' => $intents->map(fn (PaymentIntent $intent): array => [
                'id' => $intent->public_ref,
                'company_name' => $intent->company?->organization_name
                    ?? $intent->company?->legal_name
                    ?? '',
                'credits' => $intent->credits,
                'amount_cents' => $intent->amount_cents,
                'reference' => $intent->provider_ref,
                'created_at' => $intent->created_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function completeTransfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_intent_id' => ['required', 'string', 'max:128'],
        ]);

        $intent = $this->paymentIntentService->findByIdentifier($validated['payment_intent_id']);

        if ($intent === null) {
            throw new ApiException('NOT_FOUND', 'Payment intent non trovato.', 404);
        }

        if ($intent->payment_method !== PaymentMethod::Transfer) {
            throw new ApiException('VALIDATION_ERROR', 'Il payment intent non è un bonifico.', 422);
        }

        if ($intent->status !== PaymentIntentStatus::Pending) {
            throw new ApiException('CONFLICT', 'Il payment intent non è in attesa.', 409);
        }

        $result = $this->paymentIntentService->complete(
            $intent,
            'admin',
            $intent->provider_ref,
        );

        if ($result['transaction'] !== null) {
            $this->auditLogService->record(
                AuditAction::WalletRecharge,
                $request->user(),
                $result['transaction'],
                [
                    'company_id' => $intent->company_id,
                    'credits' => $intent->credits,
                    'amount_cents' => $intent->amount_cents,
                    'payment_method' => $intent->payment_method?->value,
                    'transaction_public_ref' => $result['transaction']->public_ref,
                    'payment_intent_id' => $intent->public_ref,
                    'provider_ref' => $intent->provider_ref,
                    'source' => 'admin_complete_transfer',
                ],
                $request,
            );
        }

        return ApiEnvelope::success([
            'payment_intent' => [
                'id' => $result['payment_intent']->public_ref,
                'status' => $result['payment_intent']->status->value,
                'reference' => $result['payment_intent']->provider_ref,
            ],
            'transaction' => $result['transaction'] !== null ? [
                'id' => $result['transaction']->public_ref,
                'status' => $result['transaction']->status->value,
            ] : null,
            'wallet' => new WalletResource($result['wallet']),
        ]);
    }
}
