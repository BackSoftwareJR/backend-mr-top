<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\WalletResource;
use App\Services\B2bOnboardingService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly B2bOnboardingService $onboardingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());
        $wallet = $this->walletService->getOrCreateWallet($company);

        return ApiEnvelope::success([
            'balance_credits' => $wallet->balance_credits,
            'total_spent' => $wallet->total_spent_credits,
            'currency' => $wallet->currency,
        ]);
    }

    public function recharge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:10', 'max:10000'],
            'payment_method' => ['required', 'in:card,transfer'],
        ]);

        $company = $this->onboardingService->companyForUser($request->user());
        $credits = (int) $validated['amount'];
        $amountCents = $credits * 100;
        $method = $validated['payment_method'] === 'transfer'
            ? PaymentMethod::Transfer
            : PaymentMethod::Card;

        $result = $this->walletService->addCredits(
            $company,
            $credits,
            $amountCents,
            $method,
        );

        return ApiEnvelope::success([
            'transaction' => [
                'id' => $result['transaction']->public_ref,
                'amount' => $amountCents / 100,
                'status' => $result['transaction']->status->value,
            ],
            'wallet' => new WalletResource($result['wallet']),
        ]);
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
            ->where('type', 'recharge')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($t, $i) => [
                'id' => 'INV-'.now()->year.'-'.str_pad((string) ($t->id), 3, '0', STR_PAD_LEFT),
                'date' => $t->created_at?->toDateString(),
                'amount' => $t->amount_cents / 100,
                'status' => $t->status->value,
            ])
            ->all();

        return ApiEnvelope::success(['invoices' => $invoices]);
    }
}
