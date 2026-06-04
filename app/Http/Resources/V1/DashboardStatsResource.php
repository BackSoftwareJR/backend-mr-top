<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardStatsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, int> $stats */
        $stats = $this->resource;

        return [
            'leads_today' => $stats['leads_today'],
            'active_leads_today' => $stats['active_leads_today'],
            'wallet_recharge_revenue_cents' => $stats['wallet_recharge_revenue_cents'],
            'wallet_recharge_revenue_today_cents' => $stats['wallet_recharge_revenue_today_cents'],
            'mrr_cents' => $stats['mrr_cents'],
            'mrr_today_cents' => $stats['mrr_today_cents'],
            'companies_pending_approval' => $stats['companies_pending_approval'],
            'pending_approvals' => $stats['pending_approvals'],
            'pending_bank_transfers_count' => $stats['pending_bank_transfers_count'],
            'active_partners' => $stats['active_partners'],
            'churn' => $stats['churn'],
            'conversion_rate' => $stats['conversion_rate'],
            'avg_deal_size' => $stats['avg_deal_size'],
        ];
    }
}
