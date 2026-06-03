<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class AdminDashboardService
{
    /**
     * @return array{
     *     leads_today: int,
     *     wallet_recharge_revenue_cents: int,
     *     wallet_recharge_revenue_today_cents: int,
     *     companies_pending_approval: int
     * }
     */
    public function stats(?Carbon $date = null): array
    {
        $date ??= now();

        return [
            'leads_today' => Lead::query()
                ->whereDate('created_at', $date->toDateString())
                ->count(),
            'wallet_recharge_revenue_cents' => (int) Transaction::query()
                ->where('type', TransactionType::Recharge)
                ->where('status', TransactionStatus::Completed)
                ->sum('amount_cents'),
            'wallet_recharge_revenue_today_cents' => (int) Transaction::query()
                ->where('type', TransactionType::Recharge)
                ->where('status', TransactionStatus::Completed)
                ->whereDate('completed_at', $date->toDateString())
                ->sum('amount_cents'),
            'companies_pending_approval' => Company::query()
                ->where('vetting_status', VettingStatus::PendingReview)
                ->count(),
        ];
    }
}
