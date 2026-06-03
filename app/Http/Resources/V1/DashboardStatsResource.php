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
            'wallet_recharge_revenue_cents' => $stats['wallet_recharge_revenue_cents'],
            'wallet_recharge_revenue_today_cents' => $stats['wallet_recharge_revenue_today_cents'],
            'companies_pending_approval' => $stats['companies_pending_approval'],
        ];
    }
}
