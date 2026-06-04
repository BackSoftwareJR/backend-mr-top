<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Payments\StripePaymentGateway;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentIntentService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly StripePaymentGateway $stripeGateway,
    ) {}

    public function createPending(
        Company $company,
        int $credits,
        int $amountCents,
        PaymentMethod $paymentMethod,
        ?string $idempotencyKey = null,
    ): PaymentIntent {
        $uuid = (string) Str::uuid();
        $publicRef = 'PI-'.now()->format('YmdHis').'-'.$company->id;

        $attributes = [
            'public_ref' => $publicRef,
            'company_id' => $company->id,
            'amount_cents' => $amountCents,
            'credits' => $credits,
            'status' => PaymentIntentStatus::Pending,
            'payment_method' => $paymentMethod,
            'idempotency_key' => $idempotencyKey,
        ];

        if ($this->stripeGateway->isConfigured() && $paymentMethod === PaymentMethod::Card) {
            $stripeIntent = $this->stripeGateway->createPaymentIntent(
                $amountCents,
                'eur',
                [
                    'company_id' => (string) $company->id,
                    'wenando_payment_intent_ref' => $publicRef,
                ],
            );

            $attributes['provider'] = 'stripe';
            $attributes['provider_ref'] = $stripeIntent['id'];
            $attributes['client_secret'] = $stripeIntent['client_secret'];
        } elseif ($paymentMethod === PaymentMethod::Transfer) {
            $attributes['provider'] = 'bank_transfer';
        } else {
            $attributes['client_secret'] = 'pi_'.$uuid.'_secret_'.Str::random(16);
        }

        $intent = PaymentIntent::query()->create($attributes);

        if ($paymentMethod === PaymentMethod::Transfer) {
            $intent->provider_ref = 'WEN-'.$intent->id;
            $intent->save();
        }

        return $intent;
    }

    /**
     * @return array{payment_intent: PaymentIntent, wallet: Wallet, transaction: Transaction|null}
     */
    public function complete(
        PaymentIntent $intent,
        string $provider,
        ?string $providerRef = null,
    ): array {
        return DB::transaction(function () use ($intent, $provider, $providerRef): array {
            $locked = PaymentIntent::query()
                ->whereKey($intent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === PaymentIntentStatus::Completed) {
                $wallet = $this->walletService->getOrCreateWallet($locked->company);

                return [
                    'payment_intent' => $locked->fresh(['transaction']),
                    'wallet' => $wallet,
                    'transaction' => $locked->transaction,
                ];
            }

            if ($locked->status === PaymentIntentStatus::Failed) {
                return [
                    'payment_intent' => $locked,
                    'wallet' => $this->walletService->getOrCreateWallet($locked->company),
                    'transaction' => null,
                ];
            }

            $result = $this->walletService->addCredits(
                $locked->company,
                $locked->credits,
                $locked->amount_cents,
                $locked->payment_method ?? PaymentMethod::Card,
            );

            $locked->status = PaymentIntentStatus::Completed;
            $locked->provider = $provider;
            $locked->provider_ref = $providerRef;
            $locked->transaction_id = $result['transaction']->id;
            $locked->completed_at = now();
            $locked->save();

            return [
                'payment_intent' => $locked->fresh(['transaction']),
                'wallet' => $result['wallet'],
                'transaction' => $result['transaction'],
            ];
        });
    }

    public function markFailed(PaymentIntent $intent, string $provider, ?string $providerRef = null): PaymentIntent
    {
        return DB::transaction(function () use ($intent, $provider, $providerRef): PaymentIntent {
            $locked = PaymentIntent::query()
                ->whereKey($intent->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== PaymentIntentStatus::Pending) {
                return $locked;
            }

            $locked->status = PaymentIntentStatus::Failed;
            $locked->provider = $provider;
            $locked->provider_ref = $providerRef;
            $locked->completed_at = now();
            $locked->save();

            return $locked;
        });
    }

    public function findForCompany(Company $company, string $identifier): ?PaymentIntent
    {
        return PaymentIntent::query()
            ->where('company_id', $company->id)
            ->where(fn ($query) => $this->applyIdentifierConstraints($query, $identifier))
            ->with('transaction')
            ->first();
    }

    public function findByIdentifier(string $identifier): ?PaymentIntent
    {
        return PaymentIntent::query()
            ->where(fn ($query) => $this->applyIdentifierConstraints($query, $identifier))
            ->with('transaction')
            ->first();
    }

    /**
     * @param  Builder<PaymentIntent>  $query
     */
    private function applyIdentifierConstraints($query, string $identifier): void
    {
        $query->where(function ($inner) use ($identifier): void {
            $inner->where('public_ref', $identifier)
                ->orWhere('uuid', $identifier)
                ->orWhere('provider_ref', $identifier);

            if (is_numeric($identifier)) {
                $inner->orWhere('id', (int) $identifier);
            }
        });
    }
}
