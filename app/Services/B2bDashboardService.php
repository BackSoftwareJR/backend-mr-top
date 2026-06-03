<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\LeadMatch;
use App\Models\Notification;

class B2bDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(Company $company): array
    {
        $unlocked = LeadMatch::query()
            ->where('company_id', $company->id)
            ->whereNotNull('unlocked_at')
            ->count();

        $wallet = $company->wallet;

        return [
            'stats' => [
                'leads_unlocked' => $unlocked,
                'conversion_rate' => $unlocked > 0 ? 0.24 : 0,
                'monthly_spend' => $wallet?->total_spent_credits ?? 0,
            ],
            'activity_feed' => [],
            'notifications_unread' => Notification::query()
                ->where('notifiable_type', Company::class)
                ->where('notifiable_id', $company->id)
                ->whereNull('read_at')
                ->count(),
        ];
    }
}
