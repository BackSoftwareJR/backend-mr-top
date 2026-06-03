<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\InsufficientCreditsException;
use App\Models\Company;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function getOrCreateWallet(Company $company): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['company_id' => $company->id],
            [
                'balance_credits' => 0,
                'total_spent_credits' => 0,
                'currency' => 'EUR',
            ],
        );
    }

    /**
     * @return array{wallet: Wallet, transaction: Transaction}
     */
    public function deductCredits(
        Company $company,
        int $credits,
        TransactionType $type,
        ?int $leadMatchId = null,
        ?string $description = null,
    ): array {
        return DB::transaction(function () use ($company, $credits, $type, $leadMatchId, $description): array {
            $wallet = Wallet::query()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->first();

            if ($wallet === null) {
                $wallet = $this->getOrCreateWallet($company);
                $wallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            }

            if ($wallet->balance_credits < $credits) {
                throw new InsufficientCreditsException([
                    'required_credits' => $credits,
                    'balance_credits' => $wallet->balance_credits,
                ]);
            }

            $wallet->balance_credits -= $credits;
            $wallet->total_spent_credits += $credits;
            $wallet->save();

            $transaction = Transaction::query()->create([
                'company_id' => $company->id,
                'wallet_id' => $wallet->id,
                'lead_match_id' => $leadMatchId,
                'public_ref' => 'TX-'.now()->format('YmdHis').'-'.$company->id,
                'type' => $type,
                'amount_cents' => 0,
                'credits_delta' => -$credits,
                'status' => TransactionStatus::Completed,
                'description' => $description ?? 'Lead unlock',
                'completed_at' => now(),
            ]);

            return [
                'wallet' => $wallet->fresh(),
                'transaction' => $transaction,
            ];
        });
    }

    /**
     * @return array{wallet: Wallet, transaction: Transaction}
     */
    public function addCredits(
        Company $company,
        int $credits,
        int $amountCents,
        PaymentMethod $paymentMethod,
        ?string $description = null,
    ): array {
        return DB::transaction(function () use ($company, $credits, $amountCents, $paymentMethod, $description): array {
            $wallet = Wallet::query()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->first();

            if ($wallet === null) {
                $wallet = $this->getOrCreateWallet($company);
                $wallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            }

            $wallet->balance_credits += $credits;
            $wallet->save();

            $transaction = Transaction::query()->create([
                'company_id' => $company->id,
                'wallet_id' => $wallet->id,
                'public_ref' => 'TX-'.now()->format('YmdHis').'-'.$company->id,
                'type' => TransactionType::Recharge,
                'amount_cents' => $amountCents,
                'credits_delta' => $credits,
                'status' => TransactionStatus::Completed,
                'payment_method' => $paymentMethod,
                'description' => $description ?? 'Ricarica crediti',
                'completed_at' => now(),
            ]);

            return [
                'wallet' => $wallet->fresh(),
                'transaction' => $transaction,
            ];
        });
    }
}
