<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Enums\AuditAction;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\WalletResource;
use App\Models\Company;
use App\Models\PaymentIntent;
use App\Services\AuditLogService;
use App\Services\B2bOnboardingService;
use App\Services\PaymentIntentService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly PaymentIntentService $paymentIntentService,
        private readonly B2bOnboardingService $onboardingService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());
        $wallet = $this->walletService->getOrCreateWallet($company);

        return ApiEnvelope::success([
            'wallet' => [
                'balance_credits' => $wallet->balance_credits,
                'total_spent' => $wallet->total_spent_credits,
                'currency' => $wallet->currency,
            ],
            'balance_credits' => $wallet->balance_credits,
            'total_spent' => $wallet->total_spent_credits,
            'currency' => $wallet->currency,
        ]);
    }

    public function recharge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:10', 'max:10000'],
            'payment_method' => ['required', 'in:card,transfer,bank_transfer'],
        ]);

        $company = $this->onboardingService->companyForUser($request->user());
        $credits = (int) $validated['amount'];
        $amountCents = $credits * 100;
        $method = in_array($validated['payment_method'], ['transfer', 'bank_transfer'], true)
            ? PaymentMethod::Transfer
            : PaymentMethod::Card;

        $idempotencyKey = $request->header('Idempotency-Key');
        $idempotencyKey = is_string($idempotencyKey) && $idempotencyKey !== ''
            ? $idempotencyKey
            : null;

        if (config('wenando.wallet_instant_recharge')) {
            return $this->rechargeInstant(
                $request,
                $company,
                $credits,
                $amountCents,
                $method,
                $idempotencyKey,
            );
        }

        $intent = $this->paymentIntentService->createPending(
            $company,
            $credits,
            $amountCents,
            $method,
            $idempotencyKey,
        );

        $wallet = $this->walletService->getOrCreateWallet($company);

        $payload = [
            'payment_intent' => $this->formatPaymentIntent($intent),
            'payment_intent_id' => $intent->public_ref,
            'client_secret' => $intent->client_secret,
            'wallet' => new WalletResource($wallet),
        ];

        if ($bankTransfer = $this->bankTransferInstructions($intent)) {
            $payload['bank_transfer'] = $bankTransfer;
        }

        return ApiEnvelope::success($payload);
    }

    public function rechargeStatus(Request $request, string $id): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());
        $intent = $this->paymentIntentService->findForCompany($company, $id);

        if ($intent === null) {
            throw new ApiException('NOT_FOUND', 'Payment intent non trovato.', 404);
        }

        $wallet = $this->walletService->getOrCreateWallet($company);

        $payload = [
            'payment_intent' => $this->formatPaymentIntent($intent),
            'wallet' => new WalletResource($wallet),
        ];

        if ($intent->transaction !== null) {
            $payload['transaction'] = [
                'id' => $intent->transaction->public_ref,
                'amount' => $intent->transaction->amount_cents / 100,
                'status' => $intent->transaction->status->value,
            ];
        }

        if ($bankTransfer = $this->bankTransferInstructions($intent)) {
            $payload['bank_transfer'] = $bankTransfer;
        }

        return ApiEnvelope::success($payload);
    }

    public function transactions(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());
        $items = $company->transactions()
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        $transactions = collect($items->items())->map(fn ($t) => [
            'id' => $t->public_ref ?? $t->uuid,
            'date' => $t->created_at?->toDateString(),
            'description' => $t->description,
            'amount' => $t->amount_cents / 100,
            'status' => $t->status->value,
        ])->all();

        return ApiEnvelope::success(
            ['transactions' => $transactions],
            200,
            [
                'page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        );
    }

    public function invoices(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());
        $invoices = $company->transactions()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->public_ref ?? $t->uuid,
                'date' => $t->created_at?->toDateString(),
                'description' => $t->description,
                'amount' => $t->amount_cents / 100,
                'status' => $t->status->value,
            ])
            ->all();

        return ApiEnvelope::success(['invoices' => $invoices]);
    }

    private function rechargeInstant(
        Request $request,
        Company $company,
        int $credits,
        int $amountCents,
        PaymentMethod $method,
        ?string $idempotencyKey,
    ): JsonResponse {
        $result = $this->walletService->addCredits(
            $company,
            $credits,
            $amountCents,
            $method,
        );

        $metadata = [
            'company_id' => $company->id,
            'credits' => $credits,
            'amount_cents' => $amountCents,
            'payment_method' => $method->value,
            'transaction_public_ref' => $result['transaction']->public_ref,
            'source' => 'instant_recharge',
        ];

        if ($idempotencyKey !== null) {
            $metadata['idempotency_key'] = $idempotencyKey;
        }

        $this->auditLogService->record(
            AuditAction::WalletRecharge,
            $request->user(),
            $result['transaction'],
            $metadata,
            $request,
        );

        return ApiEnvelope::success([
            'transaction' => [
                'id' => $result['transaction']->public_ref,
                'amount' => $amountCents / 100,
                'status' => $result['transaction']->status->value,
            ],
            'wallet' => new WalletResource($result['wallet']),
            'payment_intent' => [
                'id' => null,
                'status' => PaymentIntentStatus::Completed->value,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPaymentIntent(PaymentIntent $intent): array
    {
        $formatted = [
            'id' => $intent->public_ref,
            'status' => $intent->status->value,
            'amount' => $intent->amount_cents / 100,
            'credits' => $intent->credits,
            'client_secret' => $intent->client_secret,
            'payment_method' => $intent->payment_method?->value,
            'reference' => $intent->provider_ref,
        ];

        return $formatted;
    }

    /**
     * @return array{iban: string, beneficiary: string, reference: string|null, amount: float, currency: string}|null
     */
    private function bankTransferInstructions(PaymentIntent $intent): ?array
    {
        if ($intent->payment_method !== PaymentMethod::Transfer) {
            return null;
        }

        return [
            'iban' => (string) config('wenando.bank_iban'),
            'beneficiary' => (string) config('wenando.bank_beneficiary'),
            'reference' => $intent->provider_ref,
            'amount' => $intent->amount_cents / 100,
            'currency' => 'EUR',
        ];
    }
}
