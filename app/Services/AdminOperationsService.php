<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AppointmentType;
use App\Enums\AuditAction;
use App\Enums\CompanyTier;
use App\Enums\DataErasureRequestStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\VettingStatus;
use App\Jobs\ProcessLeadMatching;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\DataErasureRequest;
use App\Models\Lead;
use App\Models\LeadMatch;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\ItalianLocationParser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminOperationsService
{
    /** @var list<string> */
    private const NORD_REGIONS = [
        'piemonte', 'lombardia', 'veneto', 'liguria', 'emilia-romagna',
        'trentino-alto-adige', 'friuli-venezia-giulia', 'valle-d-aosta',
    ];

    /** @var list<string> */
    private const CENTRO_REGIONS = [
        'lazio', 'toscana', 'marche', 'umbria', 'abruzzo',
    ];

    /** Credits below this block marketplace unlock (see LeadMarketplaceService::UNLOCK_COST). */
    private const LOW_WALLET_CREDITS_THRESHOLD = 15;

    public function __construct(
        private readonly PartnerApprovalService $partnerApprovalService,
        private readonly AuditLogService $auditLogService,
        private readonly ImpersonationSessionService $impersonationSessionService,
        private readonly ItalianLocationParser $locationParser,
    ) {}

    /**
     * @return array{partners: list<array<string, mixed>>}
     */
    public function listPartners(?string $stato = null): array
    {
        $query = Company::query()->latest();

        if ($stato !== null) {
            $vetting = match (strtolower($stato)) {
                'pending' => VettingStatus::PendingReview,
                'active' => VettingStatus::Approved,
                'suspended' => VettingStatus::Suspended,
                default => null,
            };
            if ($vetting !== null) {
                $query->where('vetting_status', $vetting);
            }
        }

        $partners = $query->get()->map(fn (Company $c) => [
            'id' => $c->uuid,
            'nome_struttura' => $c->organization_name,
            'partita_iva' => $c->vat_number,
            'stato' => $this->adminPartnerStato($c),
            'citta' => $c->city,
            'submitted_at' => $c->updated_at?->toIso8601String(),
        ])->all();

        return ['partners' => $partners];
    }

    /**
     * @return array<string, mixed>
     */
    public function partnerDetail(Company $company): array
    {
        $trustTest = $company->trustTests()->latest()->first();

        return [
            'company' => $company,
            'documents' => $company->documents()->get(),
            'trust_test' => $trustTest,
            'trust_score' => $company->latestTrustScore,
        ];
    }

    public function suspend(
        Company $company,
        User $admin,
        ?string $reason = null,
        ?Request $request = null,
    ): Company {
        $company->update([
            'vetting_status' => VettingStatus::Suspended,
            'rejection_reason' => $reason,
        ]);

        $this->auditLogService->record(
            AuditAction::PartnerSuspended,
            $admin,
            $company,
            [
                'admin_uuid' => $admin->uuid,
                'company_uuid' => $company->uuid,
                'reason' => $reason,
            ],
            $request,
        );

        return $company->fresh();
    }

    /**
     * @return array{
     *     impersonation_token: string,
     *     expires_at: string,
     *     partner: array{id: string, email: string, organization_name: string}
     * }
     */
    public function impersonate(Company $company, User $admin, Request $request): array
    {
        $partnerUser = $company->users()->first();

        if ($partnerUser === null) {
            throw new HttpException(422, 'Nessun utente partner associato a questa azienda.');
        }

        $expiresAt = now()->addMinutes(15);
        $newToken = $partnerUser->createToken('impersonation', ['*'], $expiresAt);

        $startAudit = $this->auditLogService->record(
            AuditAction::ImpersonateStart,
            $admin,
            $company,
            [
                'admin_uuid' => $admin->uuid,
                'company_uuid' => $company->uuid,
                'partner_user_id' => $partnerUser->id,
                'partner_user_uuid' => $partnerUser->uuid,
                'expires_at' => $expiresAt->toIso8601String(),
                'user_agent' => $this->auditLogService->truncateUserAgent($request->userAgent()),
            ],
            $request,
        );

        $this->impersonationSessionService->start(
            $admin,
            $company,
            $partnerUser,
            $newToken->accessToken,
            $startAudit,
            $expiresAt,
        );

        return [
            'impersonation_token' => $newToken->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'partner' => [
                'id' => $partnerUser->uuid,
                'email' => $partnerUser->email,
                'organization_name' => $company->organization_name,
            ],
        ];
    }

    /**
     * @return array{
     *     partners: list<array{type: string, id: string, label: string, subtitle: string}>,
     *     leads: list<array{type: string, id: string, label: string, subtitle: string}>,
     *     transactions: list<array{type: string, id: string, label: string, subtitle: string}>,
     *     advisor_bookings: list<array{type: string, id: string, label: string, subtitle: string}>
     * }
     */
    public function search(string $query, int $limit = 5): array
    {
        $term = trim($query);
        $like = '%'.addcslashes($term, '%_\\').'%';

        $partners = Company::query()
            ->where(function ($builder) use ($like, $term): void {
                $builder
                    ->where('organization_name', 'like', $like)
                    ->orWhere('legal_name', 'like', $like)
                    ->orWhere('vat_number', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('uuid', $term);
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Company $company): array => [
                'type' => 'partner',
                'id' => $company->uuid,
                'label' => $company->organization_name,
                'subtitle' => collect([$company->city, $company->vat_number ? 'P.IVA '.$company->vat_number : null])
                    ->filter()
                    ->implode(' · '),
            ])
            ->all();

        $leadsQuery = Lead::query()
            ->where(function ($builder) use ($like): void {
                $builder
                    ->where('contact_name', 'like', $like)
                    ->orWhere('contact_email', 'like', $like)
                    ->orWhere('public_ref', 'like', $like)
                    ->orWhere('location_label', 'like', $like);
            });

        if (ctype_digit($term)) {
            $leadsQuery->orWhere('id', (int) $term);
        }

        $leads = $leadsQuery
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Lead $lead): array => [
                'type' => 'lead',
                'id' => (string) $lead->id,
                'label' => $lead->contact_name ?? $lead->public_ref ?? 'Lead #'.$lead->id,
                'subtitle' => $lead->public_ref ?? $lead->location_label ?? '',
            ])
            ->all();

        $transactions = Transaction::query()
            ->with('company')
            ->where(function ($builder) use ($like): void {
                $builder
                    ->where('public_ref', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhereHas('company', fn ($companyQuery) => $companyQuery->where('organization_name', 'like', $like));
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $transaction): array => [
                'type' => 'transaction',
                'id' => $transaction->public_ref ?? $transaction->uuid,
                'label' => $transaction->public_ref ?? $transaction->uuid,
                'subtitle' => $transaction->company?->organization_name ?? '',
            ])
            ->all();

        $advisorBookings = Appointment::query()
            ->where('type', AppointmentType::Advisor)
            ->with('lead:id,title')
            ->where(function ($builder) use ($like): void {
                $builder
                    ->where('client_name', 'like', $like)
                    ->orWhereHas('lead', fn ($leadQuery) => $leadQuery->where('title', 'like', $like));
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (Appointment $appointment): array {
                $dateLabel = $appointment->scheduled_date?->format('d/m/Y') ?? '';
                $timeLabel = substr((string) $appointment->scheduled_time, 0, 5);
                $schedule = collect([$dateLabel, $timeLabel])->filter()->implode(' · ');

                return [
                    'type' => 'advisor_booking',
                    'id' => (string) $appointment->id,
                    'label' => $appointment->client_name,
                    'subtitle' => collect([$appointment->lead?->title, $schedule])
                        ->filter()
                        ->implode(' · '),
                ];
            })
            ->all();

        return [
            'partners' => $partners,
            'leads' => $leads,
            'transactions' => $transactions,
            'advisor_bookings' => $advisorBookings,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Lead>
     */
    public function listLeads(int $perPage = 20): LengthAwarePaginator
    {
        return Lead::query()->with('leadMatches.company')->latest()->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function leadDetail(int $leadId): array
    {
        $lead = Lead::query()->with(['leadMatches.company', 'sector'])->findOrFail($leadId);

        return [
            'lead' => $lead,
            'matches' => $lead->leadMatches,
        ];
    }

    /**
     * @return array{lead: Lead, assignment: LeadMatch}
     */
    public function assignPartner(
        Lead $lead,
        int $companyId,
        User $admin,
        ?Request $request = null,
    ): array {
        return DB::transaction(function () use ($lead, $companyId, $admin, $request): array {
            $company = Company::query()->findOrFail($companyId);

            $match = LeadMatch::query()->updateOrCreate(
                [
                    'lead_id' => $lead->id,
                    'company_id' => $company->id,
                ],
                [
                    'match_score' => 100,
                    'rank' => 1,
                    'is_visible_to_consumer' => true,
                    'is_in_marketplace' => true,
                    'assigned_by' => $admin->id,
                    'metadata' => [
                        'manual_lock' => true,
                        'ai_match_label' => sprintf('%s (override)', $company->organization_name),
                    ],
                ],
            );

            $lead->update([
                'status' => LeadStatus::Assigned,
                'admin_status' => 'Assegnato',
            ]);

            $this->auditLogService->record(
                AuditAction::LeadManualAssign,
                $admin,
                $lead,
                [
                    'admin_uuid' => $admin->uuid,
                    'lead_id' => $lead->id,
                    'lead_uuid' => $lead->uuid,
                    'company_id' => $company->id,
                    'company_uuid' => $company->uuid,
                    'lead_match_id' => $match->id,
                ],
                $request,
            );

            return ['lead' => $lead->fresh(), 'assignment' => $match];
        });
    }

    public function reroute(Lead $lead, User $admin, ?Request $request = null): array
    {
        ProcessLeadMatching::dispatch($lead->id);

        $this->auditLogService->record(
            AuditAction::LeadReroute,
            $admin,
            $lead,
            [
                'admin_uuid' => $admin->uuid,
                'lead_id' => $lead->id,
                'lead_uuid' => $lead->uuid,
            ],
            $request,
        );

        return ['job_id' => (string) Str::ulid()];
    }

    /**
     * @return array{summary: array<string, array{value: string, count: int}>, transactions: list<array<string, mixed>>}
     */
    public function transactions(?string $status = null, int $perPage = 20): array
    {
        $query = Transaction::query()->with('company')->latest();

        if ($status !== null) {
            $query->where('status', $status);
        }

        $items = $query->limit($perPage)->get();

        return [
            'summary' => [
                'today' => $this->transactionPeriodSummary(today(), today()),
                'week' => $this->transactionPeriodSummary(now()->subWeek(), now()),
                'month' => $this->transactionPeriodSummary(now()->subMonth(), now()),
            ],
            'transactions' => $items->map(fn (Transaction $t) => [
                'id' => $t->public_ref ?? $t->uuid,
                'partner' => $t->company?->organization_name,
                'importo' => $t->amount_cents / 100,
                'stato' => $this->transactionStatoLabel($t->status),
                'data' => $t->created_at?->toDateString(),
                'tipo' => $this->transactionTipoLabel($t->type),
            ])->all(),
        ];
    }

    /**
     * @return array{value: string, count: int}
     */
    private function transactionPeriodSummary(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $baseQuery = Transaction::query()
            ->where('status', TransactionStatus::Completed)
            ->whereBetween('created_at', [$from, $to]);

        $count = (int) (clone $baseQuery)->count();
        $totalCents = (int) (clone $baseQuery)->sum('amount_cents');

        return [
            'value' => number_format($totalCents / 100, 0, ',', '.').'€',
            'count' => $count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transactionDetail(Transaction $transaction): array
    {
        return [
            'id' => $transaction->public_ref ?? $transaction->uuid,
            'partner' => $transaction->company?->organization_name,
            'importo' => $transaction->amount_cents / 100,
            'stato' => $this->transactionStatoLabel($transaction->status),
            'data' => $transaction->created_at?->toDateString(),
            'tipo' => $this->transactionTipoLabel($transaction->type),
            'metodo' => $transaction->payment_method?->value,
            'riferimento' => $transaction->reference,
            'note' => $transaction->description,
        ];
    }

    /**
     * @return array{points: list<array{day: string, amount: float}>}
     */
    public function revenueTimeline(int $days = 7): array
    {
        $points = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $amount = Transaction::query()
                ->where('type', TransactionType::Recharge)
                ->where('status', TransactionStatus::Completed)
                ->whereDate('completed_at', $day)
                ->sum('amount_cents') / 100;
            $points[] = ['day' => $day, 'amount' => (float) $amount];
        }

        return ['points' => $points];
    }

    /**
     * @return array{points: list<array{day: string, leads: int, revenue: float}>}
     */
    public function leadsFlow(int $days = 14): array
    {
        $points = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $points[] = [
                'day' => $day,
                'leads' => Lead::query()->whereDate('created_at', $day)->count(),
                'revenue' => (float) Transaction::query()
                    ->whereDate('completed_at', $day)
                    ->sum('amount_cents') / 100,
            ];
        }

        return ['points' => $points];
    }

    /**
     * @return array<string, mixed>
     */
    public function portfolioSummary(): array
    {
        $aumByCompany = $this->completedAumByCompany();
        $totalAumCents = (int) $aumByCompany->sum();
        $partnerCount = $aumByCompany->count();
        $avgAumCents = $partnerCount > 0 ? (int) round($totalAumCents / $partnerCount) : 0;

        $revenueCents = (int) Transaction::query()
            ->where('type', TransactionType::Recharge)
            ->where('status', TransactionStatus::Completed)
            ->sum('amount_cents');

        $currentMonthCents = (int) Transaction::query()
            ->where('status', TransactionStatus::Completed)
            ->where('completed_at', '>=', now()->startOfMonth())
            ->sum('amount_cents');

        $previousMonthStart = now()->subMonth()->startOfMonth();
        $previousMonthEnd = now()->subMonth()->endOfMonth();
        $previousMonthCents = (int) Transaction::query()
            ->where('status', TransactionStatus::Completed)
            ->whereBetween('completed_at', [$previousMonthStart, $previousMonthEnd])
            ->sum('amount_cents');

        $growthPercent = $previousMonthCents > 0
            ? (($currentMonthCents - $previousMonthCents) / $previousMonthCents) * 100
            : ($currentMonthCents > 0 ? 100.0 : 0.0);

        $activeContracts = Company::query()
            ->where('vetting_status', VettingStatus::Approved)
            ->whereIn('id', $aumByCompany->keys()->all())
            ->count();

        return [
            'total_aum' => $this->formatCompactEuro($totalAumCents),
            'revenue_under_management' => $this->formatPortfolioEuro($revenueCents),
            'monthly_growth' => $this->formatGrowthPercent($growthPercent),
            'active_contracts' => $activeContracts,
            'avg_exposure' => $this->exposureLabel($avgAumCents),
        ];
    }

    /**
     * @return array{by_sector: list<array>, by_region: list<array>, by_tier: list<array>}
     */
    public function portfolioAllocation(): array
    {
        $aumByCompany = $this->completedAumByCompany();
        $totalAumCents = (int) $aumByCompany->sum();

        if ($totalAumCents === 0) {
            return [
                'by_sector' => [],
                'by_region' => [],
                'by_tier' => [],
            ];
        }

        $companies = Company::query()
            ->where('vetting_status', VettingStatus::Approved)
            ->whereIn('id', $aumByCompany->keys()->all())
            ->with('sector:id,name')
            ->get(['id', 'sector_id', 'city', 'tier']);

        $bySector = [];
        $byRegion = [];
        $byTier = [];

        foreach ($companies as $company) {
            $aumCents = (int) ($aumByCompany[$company->id] ?? 0);
            if ($aumCents <= 0) {
                continue;
            }

            $sectorLabel = $company->sector?->name ?? 'Altro';
            $bySector[$sectorLabel] = ($bySector[$sectorLabel] ?? 0) + $aumCents;

            $regionLabel = $this->macroRegionLabel($company->city);
            $byRegion[$regionLabel] = ($byRegion[$regionLabel] ?? 0) + $aumCents;

            $tierLabel = $this->tierLabel($company->tier);
            $byTier[$tierLabel] = ($byTier[$tierLabel] ?? 0) + $aumCents;
        }

        return [
            'by_sector' => $this->allocationSegmentsFromTotals($bySector, $totalAumCents),
            'by_region' => $this->allocationSegmentsFromTotals($byRegion, $totalAumCents),
            'by_tier' => $this->allocationSegmentsFromTotals($byTier, $totalAumCents),
        ];
    }

    /**
     * @return array{partners: list<array<string, mixed>>}
     */
    public function portfolioPartners(): array
    {
        $aumByCompany = $this->completedAumByCompany();
        $totalAumCents = (int) $aumByCompany->sum();

        if ($totalAumCents === 0) {
            return ['partners' => []];
        }

        $monthlyExposureByCompany = Transaction::query()
            ->select('company_id', DB::raw('SUM(amount_cents) as exposure_cents'))
            ->where('status', TransactionStatus::Completed)
            ->where('completed_at', '>=', now()->startOfMonth())
            ->groupBy('company_id')
            ->pluck('exposure_cents', 'company_id');

        $trendsByCompany = $this->completedMonthlyTrendsByCompany($aumByCompany->keys(), 6);

        $partners = Company::query()
            ->where('vetting_status', VettingStatus::Approved)
            ->whereIn('id', $aumByCompany->keys()->all())
            ->get(['id', 'uuid', 'organization_name', 'city', 'tier'])
            ->sortByDesc(fn (Company $company): int => (int) ($aumByCompany[$company->id] ?? 0))
            ->take(20)
            ->values()
            ->map(function (Company $company) use ($aumByCompany, $totalAumCents, $monthlyExposureByCompany, $trendsByCompany): array {
                $aumCents = (int) ($aumByCompany[$company->id] ?? 0);
                $exposureCents = (int) ($monthlyExposureByCompany[$company->id] ?? 0);
                $revenueShare = $totalAumCents > 0
                    ? (int) round(($aumCents / $totalAumCents) * 100)
                    : 0;

                return [
                    'id' => $company->uuid,
                    'nome' => $company->organization_name,
                    'tier' => $this->tierLabel($company->tier),
                    'aum' => $this->formatCompactEuro($aumCents),
                    'revenue_share' => $revenueShare,
                    'trend' => $trendsByCompany[$company->id] ?? array_fill(0, 6, 0),
                    'risk' => $this->partnerRiskLabel($aumCents),
                    'exposure' => $this->formatCompactEuro($exposureCents),
                    'citta' => $company->city ?? '',
                ];
            })
            ->all();

        return ['partners' => $partners];
    }

    /**
     * Last N calendar months of completed transaction sums per company (oldest first).
     *
     * @param  Collection<int|string, mixed>  $companyIds
     * @return array<int, list<int>>
     */
    private function completedMonthlyTrendsByCompany(Collection $companyIds, int $months = 6): array
    {
        if ($companyIds->isEmpty()) {
            return [];
        }

        $start = now()->subMonths($months - 1)->startOfMonth();
        $monthKeys = collect(range(0, $months - 1))
            ->map(fn (int $offset): string => $start->copy()->addMonths($offset)->format('Y-m'))
            ->all();

        $rows = Transaction::query()
            ->select('company_id', 'amount_cents', 'completed_at')
            ->where('status', TransactionStatus::Completed)
            ->whereIn('company_id', $companyIds->all())
            ->where('completed_at', '>=', $start)
            ->get();

        /** @var array<int, array<string, int>> $indexed */
        $indexed = [];
        foreach ($rows as $row) {
            $monthKey = $row->completed_at->format('Y-m');
            $companyId = (int) $row->company_id;
            $indexed[$companyId][$monthKey] = ($indexed[$companyId][$monthKey] ?? 0) + (int) $row->amount_cents;
        }

        $trends = [];
        foreach ($companyIds as $companyId) {
            $id = (int) $companyId;
            $trends[$id] = array_map(
                fn (string $key): int => (int) ($indexed[$id][$key] ?? 0),
                $monthKeys,
            );
        }

        return $trends;
    }

    /**
     * @return Collection<int, int> company_id => aum_cents
     */
    private function completedAumByCompany(): Collection
    {
        return Transaction::query()
            ->select('company_id', DB::raw('SUM(amount_cents) as aum_cents'))
            ->where('status', TransactionStatus::Completed)
            ->groupBy('company_id')
            ->pluck('aum_cents', 'company_id')
            ->map(fn ($cents): int => (int) $cents);
    }

    /**
     * @param  array<string, int>  $totals
     * @return list<array{label: string, percent: int, value: int}>
     */
    private function allocationSegmentsFromTotals(array $totals, int $totalAumCents): array
    {
        if ($totalAumCents === 0 || $totals === []) {
            return [];
        }

        arsort($totals);

        $segments = [];
        $percentSum = 0;

        foreach ($totals as $label => $amountCents) {
            $percent = (int) round(($amountCents / $totalAumCents) * 100);
            $percentSum += $percent;
            $segments[] = [
                'label' => $label,
                'percent' => $percent,
                'value' => $percent,
            ];
        }

        if ($segments !== [] && $percentSum !== 100) {
            $segments[0]['percent'] += 100 - $percentSum;
            $segments[0]['value'] = $segments[0]['percent'];
        }

        return $segments;
    }

    private function macroRegionLabel(?string $city): string
    {
        $region = $this->locationParser->parse($city)->region;

        if ($region === null) {
            return 'Altro';
        }

        if (in_array($region, self::NORD_REGIONS, true)) {
            return 'Nord';
        }

        if (in_array($region, self::CENTRO_REGIONS, true)) {
            return 'Centro';
        }

        return 'Sud';
    }

    private function tierLabel(?CompanyTier $tier): string
    {
        return match ($tier) {
            CompanyTier::Enterprise => 'Enterprise',
            CompanyTier::Growth => 'Growth',
            CompanyTier::Starter => 'Starter',
            default => 'Starter',
        };
    }

    private function exposureLabel(int $avgAumCents): string
    {
        if ($avgAumCents >= 20_000_000) {
            return 'Alto';
        }

        if ($avgAumCents >= 5_000_000) {
            return 'Medio';
        }

        return 'Basso';
    }

    private function partnerRiskLabel(int $aumCents): string
    {
        if ($aumCents >= 20_000_000) {
            return 'Basso';
        }

        if ($aumCents >= 5_000_000) {
            return 'Medio';
        }

        return 'Alto';
    }

    private function formatPortfolioEuro(int $cents): string
    {
        return '€ '.number_format($cents / 100, 0, ',', '.');
    }

    private function formatCompactEuro(int $cents): string
    {
        $euros = $cents / 100;

        if ($euros >= 1_000_000) {
            return '€ '.number_format($euros / 1_000_000, 2, ',', '.').'M';
        }

        if ($euros >= 1_000) {
            return '€ '.number_format($euros / 1_000, 0, ',', '.').'K';
        }

        return $this->formatPortfolioEuro($cents);
    }

    private function formatGrowthPercent(float $percent): string
    {
        $sign = $percent >= 0 ? '+' : '';

        return $sign.number_format($percent, 1, ',', '.').'%';
    }

    /**
     * God Mode alerts derived from pending vetting, unassigned leads, and suspensions.
     *
     * @return array{notifications: list<array<string, mixed>>}
     */
    public function listNotifications(int $limit = 50): array
    {
        $notifications = [];

        Company::query()
            ->where('vetting_status', VettingStatus::PendingReview)
            ->latest()
            ->limit(20)
            ->get()
            ->each(function (Company $company) use (&$notifications): void {
                $notifications[] = [
                    'id' => 'partner-pending-'.$company->uuid,
                    'title' => 'Nuova registrazione partner',
                    'message' => sprintf('%s in attesa di approvazione', $company->organization_name),
                    'created_at' => $company->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                    'read' => false,
                ];
            });

        Lead::query()
            ->whereIn('status', [LeadStatus::Processing, LeadStatus::Routed])
            ->whereDoesntHave('leadMatches', fn ($query) => $query->whereNotNull('assigned_by'))
            ->latest()
            ->limit(20)
            ->get()
            ->each(function (Lead $lead) use (&$notifications): void {
                $ref = $lead->public_ref ?? (string) $lead->id;
                $notifications[] = [
                    'id' => 'lead-unassigned-'.$lead->id,
                    'title' => 'Lead ad alta priorità',
                    'message' => sprintf('%s richiede override manuale', $ref),
                    'created_at' => $lead->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'read' => false,
                ];
            });

        DataErasureRequest::query()
            ->with('user')
            ->whereIn('status', [DataErasureRequestStatus::Pending, DataErasureRequestStatus::Processing])
            ->latest('requested_at')
            ->limit(20)
            ->get()
            ->each(function (DataErasureRequest $erasureRequest) use (&$notifications): void {
                $email = $erasureRequest->user?->email ?? 'utente';
                $statusLabel = $erasureRequest->status === DataErasureRequestStatus::Processing
                    ? 'in elaborazione'
                    : 'in attesa di revisione';

                $notifications[] = [
                    'id' => 'erasure-request-'.$erasureRequest->id,
                    'title' => 'Richiesta cancellazione dati',
                    'message' => sprintf('%s — %s', $email, $statusLabel),
                    'created_at' => $erasureRequest->requested_at?->toIso8601String()
                        ?? $erasureRequest->created_at?->toIso8601String()
                        ?? now()->toIso8601String(),
                    'read' => false,
                    'href' => '/admin/settings?section=privacy',
                ];
            });

        Appointment::query()
            ->where('type', AppointmentType::Advisor)
            ->latest()
            ->limit(20)
            ->get()
            ->each(function (Appointment $appointment) use (&$notifications): void {
                $dateLabel = $appointment->scheduled_date?->format('d/m/Y') ?? '';
                $timeLabel = substr((string) $appointment->scheduled_time, 0, 5);

                $notifications[] = [
                    'id' => 'advisor-booking-'.$appointment->id,
                    'title' => 'Nuova consulenza advisor',
                    'message' => sprintf(
                        '%s — %s alle %s',
                        $appointment->client_name,
                        $dateLabel,
                        $timeLabel,
                    ),
                    'created_at' => $appointment->created_at?->toIso8601String() ?? now()->toIso8601String(),
                    'read' => false,
                    'href' => '/admin/advisor-bookings',
                ];
            });

        Company::query()
            ->where('vetting_status', VettingStatus::Suspended)
            ->latest()
            ->limit(10)
            ->get()
            ->each(function (Company $company) use (&$notifications): void {
                $notifications[] = [
                    'id' => 'partner-suspended-'.$company->uuid,
                    'title' => 'Partner sospeso',
                    'message' => sprintf('%s — verifica documenti', $company->organization_name),
                    'created_at' => $company->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                    'read' => false,
                ];
            });

        usort(
            $notifications,
            fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']),
        );

        return ['notifications' => array_slice($notifications, 0, $limit)];
    }

    /**
     * God Mode risk widgets from vetting, lead routing, wallet, and payment signals.
     *
     * @return array{indicators: list<array<string, mixed>>}
     */
    public function riskIndicators(): array
    {
        $pendingPartners = Company::query()
            ->where('vetting_status', VettingStatus::PendingReview)
            ->count();

        $unassignedLeads = Lead::query()
            ->whereIn('status', [LeadStatus::Processing, LeadStatus::Routed])
            ->whereDoesntHave('leadMatches', fn ($query) => $query->whereNotNull('assigned_by'))
            ->count();

        $suspendedPartners = Company::query()
            ->where('vetting_status', VettingStatus::Suspended)
            ->count();

        $lowWalletPartners = Wallet::query()
            ->where('balance_credits', '<', self::LOW_WALLET_CREDITS_THRESHOLD)
            ->whereHas(
                'company',
                fn ($query) => $query->where('vetting_status', VettingStatus::Approved),
            )
            ->count();

        $pendingBankTransfers = PaymentIntent::query()
            ->where('status', PaymentIntentStatus::Pending)
            ->where(function ($query): void {
                $query->where('payment_method', PaymentMethod::Transfer)
                    ->orWhere('payment_method', 'bank_transfer');
            })
            ->count();

        $leadsInMatching = Lead::query()
            ->where('status', LeadStatus::Processing)
            ->count();

        return [
            'indicators' => [
                $this->riskIndicator(
                    'Partner in attesa',
                    $pendingPartners,
                    $pendingPartners > 0 ? 'warn' : 'ok',
                    'Registrazioni da approvare in God Mode',
                ),
                $this->riskIndicator(
                    'Lead non assegnati',
                    $unassignedLeads,
                    $unassignedLeads > 0 ? 'alert' : 'ok',
                    'Lead in routing senza override manuale',
                ),
                $this->riskIndicator(
                    'Partner sospesi',
                    $suspendedPartners,
                    $suspendedPartners > 0 ? 'warn' : 'ok',
                    'Account partner sospesi',
                ),
                $this->riskIndicator(
                    'Wallet partner bassi',
                    $lowWalletPartners,
                    $lowWalletPartners > 0 ? 'warn' : 'ok',
                    sprintf('Saldo sotto %d crediti (unlock lead)', self::LOW_WALLET_CREDITS_THRESHOLD),
                ),
                $this->riskIndicator(
                    'Bonifici in attesa',
                    $pendingBankTransfers,
                    $pendingBankTransfers > 0 ? 'warn' : 'ok',
                    'Ricariche bonifico da confermare',
                ),
                $this->riskIndicator(
                    'Lead in elaborazione',
                    $leadsInMatching,
                    $leadsInMatching > 10 ? 'warn' : 'ok',
                    'Lead in coda matching automatico',
                ),
            ],
        ];
    }

    /**
     * @return array{label: string, value: int, status: string, detail: string}
     */
    private function riskIndicator(string $label, int $value, string $status, string $detail): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'status' => $status,
            'detail' => $detail,
        ];
    }

    private function adminPartnerStato(Company $company): string
    {
        return match ($company->vetting_status) {
            VettingStatus::PendingReview => 'Pending',
            VettingStatus::Approved => 'Active',
            VettingStatus::Suspended => 'Suspended',
            default => 'Pending',
        };
    }

    private function transactionStatoLabel(TransactionStatus $status): string
    {
        return match ($status) {
            TransactionStatus::Completed => 'Completata',
            TransactionStatus::Pending => 'In attesa',
            TransactionStatus::Failed => 'Fallita',
            default => $status->value,
        };
    }

    private function transactionTipoLabel(TransactionType $type): string
    {
        return match ($type) {
            TransactionType::Recharge => 'Abbonamento mensile',
            TransactionType::LeadBundle => 'Lead bundle',
            TransactionType::Commission => 'Commissione',
            TransactionType::LeadUnlock => 'Lead singolo',
            default => $type->value,
        };
    }
}
