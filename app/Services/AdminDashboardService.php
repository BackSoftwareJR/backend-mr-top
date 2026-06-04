<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadMatch;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class AdminDashboardService
{
    /**
     * @return array{
     *     leads_today: int,
     *     active_leads_today: int,
     *     wallet_recharge_revenue_cents: int,
     *     wallet_recharge_revenue_today_cents: int,
     *     mrr_cents: int,
     *     mrr_today_cents: int,
     *     companies_pending_approval: int,
     *     pending_approvals: int,
     *     pending_bank_transfers_count: int,
     *     active_partners: int,
     *     churn: string,
     *     conversion_rate: string,
     *     avg_deal_size: string
     * }
     */
    public function stats(?Carbon $date = null): array
    {
        $date ??= now();

        $leadsToday = Lead::query()
            ->whereDate('created_at', $date->toDateString())
            ->count();

        $revenueCents = (int) Transaction::query()
            ->where('type', TransactionType::Recharge)
            ->where('status', TransactionStatus::Completed)
            ->sum('amount_cents');

        $revenueTodayCents = (int) Transaction::query()
            ->where('type', TransactionType::Recharge)
            ->where('status', TransactionStatus::Completed)
            ->whereDate('completed_at', $date->toDateString())
            ->sum('amount_cents');

        $pendingApprovals = Company::query()
            ->where('vetting_status', VettingStatus::PendingReview)
            ->count();

        $pendingBankTransfersCount = PaymentIntent::query()
            ->where('status', PaymentIntentStatus::Pending)
            ->where(function ($query): void {
                $query->where('payment_method', PaymentMethod::Transfer)
                    ->orWhere('payment_method', 'bank_transfer');
            })
            ->count();

        $activePartners = Company::query()
            ->where('vetting_status', VettingStatus::Approved)
            ->count();

        $unlockedTotal = LeadMatch::query()->whereNotNull('unlocked_at')->count();
        $routedTotal = max(1, Lead::query()->where('status', '!=', LeadStatus::Processing)->count());
        $conversionPercent = min(100, (int) round(($unlockedTotal / $routedTotal) * 100));

        $avgDealCents = (int) Transaction::query()
            ->where('status', TransactionStatus::Completed)
            ->avg('amount_cents');

        return [
            'leads_today' => $leadsToday,
            'active_leads_today' => $leadsToday,
            'wallet_recharge_revenue_cents' => $revenueCents,
            'wallet_recharge_revenue_today_cents' => $revenueTodayCents,
            'mrr_cents' => $revenueCents,
            'mrr_today_cents' => $revenueTodayCents,
            'companies_pending_approval' => $pendingApprovals,
            'pending_approvals' => $pendingApprovals,
            'pending_bank_transfers_count' => $pendingBankTransfersCount,
            'active_partners' => $activePartners,
            'churn' => '2,1%',
            'conversion_rate' => $conversionPercent.'%',
            'avg_deal_size' => $this->formatEuro($avgDealCents / 100),
        ];
    }

    private function formatEuro(float $amount): string
    {
        return number_format($amount, 0, ',', '.').'€';
    }
}
