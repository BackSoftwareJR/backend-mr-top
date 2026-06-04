<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CrmStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\VettingStatus;
use App\Models\ActivityFeed;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\LeadMatch;
use App\Models\Notification;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class B2bDashboardService
{
    private const ACTIVITY_LIMIT = 20;

    private const TREND_DAYS = 7;

    public function __construct(
        private readonly LeadMarketplaceService $marketplaceService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(Company $company): array
    {
        $wallet = $company->wallet;

        $unlocked = LeadMatch::query()
            ->where('company_id', $company->id)
            ->whereNotNull('unlocked_at')
            ->count();

        $closed = LeadMatch::query()
            ->where('company_id', $company->id)
            ->whereNotNull('unlocked_at')
            ->where('crm_status', CrmStatus::Chiuso)
            ->count();

        $monthlySpend = (int) Transaction::query()
            ->where('company_id', $company->id)
            ->where('type', TransactionType::LeadUnlock)
            ->where('status', TransactionStatus::Completed)
            ->where('completed_at', '>=', now()->startOfMonth())
            ->sum(DB::raw('ABS(credits_delta)'));

        return [
            'stats' => [
                'wallet_balance_credits' => $wallet?->balance_credits ?? 0,
                'leads_unlocked' => $unlocked,
                'marketplace_available' => $this->countMarketplaceAvailable($company),
                'conversion_rate' => $unlocked > 0 ? round($closed / $unlocked, 4) : 0.0,
                'monthly_spend' => $monthlySpend,
            ],
            'activity_feed' => $this->buildActivityFeed($company),
            'leads_trend' => $this->buildLeadsTrend($company),
            'notifications_unread' => Notification::query()
                ->where('notifiable_type', Company::class)
                ->where('notifiable_id', $company->id)
                ->whereNull('read_at')
                ->count(),
        ];
    }

    private function countMarketplaceAvailable(Company $company): int
    {
        if ($company->vetting_status !== VettingStatus::Approved) {
            return 0;
        }

        return $this->marketplaceService
            ->listCompatibleLeads($company)
            ->whereNull('unlocked_at')
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildActivityFeed(Company $company): array
    {
        $persisted = ActivityFeed::query()
            ->where('company_id', $company->id)
            ->latest('created_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get();

        if ($persisted->isNotEmpty()) {
            return $persisted
                ->map(fn (ActivityFeed $entry): array => [
                    'id' => 'ACT-'.$entry->id,
                    'type' => $entry->type,
                    'text' => $entry->text,
                    'created_at' => $entry->created_at?->toIso8601String(),
                    'time' => $entry->created_at !== null
                        ? $this->formatRelativeTime($entry->created_at)
                        : null,
                ])
                ->values()
                ->all();
        }

        return $this->buildComputedActivityFeed($company);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildComputedActivityFeed(Company $company): array
    {
        $items = [];

        $transactions = Transaction::query()
            ->where('company_id', $company->id)
            ->where('status', TransactionStatus::Completed)
            ->whereIn('type', [TransactionType::Recharge, TransactionType::LeadUnlock])
            ->with('leadMatch.lead')
            ->latest('completed_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get();

        foreach ($transactions as $transaction) {
            $timestamp = $transaction->completed_at ?? $transaction->created_at;

            $items[] = [
                'id' => 'ACT-TX-'.$transaction->id,
                'type' => $transaction->type === TransactionType::Recharge ? 'recharge' : 'unlock',
                'text' => $this->transactionActivityText($transaction),
                'created_at' => $timestamp?->toIso8601String(),
                'sort_at' => $timestamp,
            ];
        }

        $crmUpdates = LeadMatch::query()
            ->where('company_id', $company->id)
            ->whereNotNull('unlocked_at')
            ->whereColumn('updated_at', '>', 'unlocked_at')
            ->with('lead')
            ->latest('updated_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get();

        foreach ($crmUpdates as $match) {
            $contactName = $match->lead?->contact_name ?? 'Lead';

            $items[] = [
                'id' => 'ACT-CRM-'.$match->id.'-'.($match->updated_at?->timestamp ?? 0),
                'type' => 'status',
                'text' => sprintf(
                    'Stato aggiornato: %s → %s',
                    $contactName,
                    $this->crmStatusLabel($match->crm_status),
                ),
                'created_at' => $match->updated_at?->toIso8601String(),
                'sort_at' => $match->updated_at,
            ];
        }

        $appointments = Appointment::query()
            ->where('company_id', $company->id)
            ->latest('created_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get();

        foreach ($appointments as $appointment) {
            $timestamp = $appointment->created_at;
            $clientName = $appointment->client_name ?? 'Cliente';
            $dateLabel = $appointment->scheduled_date?->locale('it')->isoFormat('D MMM') ?? '';
            $timeLabel = $appointment->scheduled_time ?? '';

            $items[] = [
                'id' => 'ACT-APT-'.$appointment->id,
                'type' => 'visit',
                'text' => trim(sprintf(
                    'Visita programmata: %s · %s %s',
                    $clientName,
                    $dateLabel,
                    $timeLabel,
                )),
                'created_at' => $timestamp?->toIso8601String(),
                'sort_at' => $timestamp,
            ];
        }

        return collect($items)
            ->filter(fn (array $item): bool => $item['sort_at'] instanceof CarbonInterface)
            ->sortByDesc(fn (array $item): int => $item['sort_at']->getTimestamp())
            ->take(self::ACTIVITY_LIMIT)
            ->map(fn (array $item): array => [
                'id' => $item['id'],
                'type' => $item['type'],
                'text' => $item['text'],
                'created_at' => $item['created_at'],
                'time' => $this->formatRelativeTime($item['sort_at']),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{day: int, date: string, leads: int}>
     */
    private function buildLeadsTrend(Company $company): array
    {
        $start = now()->subDays(self::TREND_DAYS - 1)->startOfDay();

        /** @var array<string, int> $counts */
        $counts = LeadMatch::query()
            ->where('company_id', $company->id)
            ->whereNotNull('unlocked_at')
            ->where('unlocked_at', '>=', $start)
            ->selectRaw('DATE(unlocked_at) as unlock_day, COUNT(*) as leads')
            ->groupBy('unlock_day')
            ->pluck('leads', 'unlock_day')
            ->all();

        $trend = [];

        for ($offset = 0; $offset < self::TREND_DAYS; $offset++) {
            $date = $start->copy()->addDays($offset);
            $dateKey = $date->toDateString();

            $trend[] = [
                'day' => $offset + 1,
                'date' => $dateKey,
                'leads' => (int) ($counts[$dateKey] ?? 0),
            ];
        }

        return $trend;
    }

    private function transactionActivityText(Transaction $transaction): string
    {
        if ($transaction->type === TransactionType::Recharge) {
            $credits = abs($transaction->credits_delta);

            return sprintf('Ricarica wallet: %d crediti', $credits);
        }

        $contactName = $transaction->leadMatch?->lead?->contact_name;

        if ($contactName !== null) {
            return sprintf('Lead sbloccato: %s', $contactName);
        }

        return $transaction->description ?? 'Lead sbloccato';
    }

    private function crmStatusLabel(?CrmStatus $status): string
    {
        return match ($status) {
            CrmStatus::Nuovo, null => 'Nuovo',
            CrmStatus::Contattato => 'Contattato',
            CrmStatus::VisitaFissata => 'Visita Fissata',
            CrmStatus::Perso => 'Perso',
            CrmStatus::Chiuso => 'Chiuso',
        };
    }

    private function formatRelativeTime(CarbonInterface $timestamp): string
    {
        $carbon = Carbon::instance($timestamp);
        $diffMin = (int) now()->diffInMinutes($carbon);

        if ($diffMin < 1) {
            return 'Adesso';
        }

        if ($diffMin < 60) {
            return sprintf('%d min fa', $diffMin);
        }

        $diffHours = (int) floor($diffMin / 60);

        if ($diffHours < 24) {
            return sprintf('%d ore fa', $diffHours);
        }

        $diffDays = (int) floor($diffHours / 24);

        if ($diffDays === 1) {
            return 'Ieri';
        }

        if ($diffDays < 7) {
            return sprintf('%d giorni fa', $diffDays);
        }

        return $carbon->locale('it')->isoFormat('D MMM');
    }
}
