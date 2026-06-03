<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\VettingStatus;
use App\Jobs\ProcessLeadMatching;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadMatch;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminOperationsService
{
    public function __construct(
        private readonly PartnerApprovalService $partnerApprovalService,
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

    public function suspend(Company $company, ?string $reason = null): Company
    {
        $company->update([
            'vetting_status' => VettingStatus::Suspended,
            'rejection_reason' => $reason,
        ]);

        return $company->fresh();
    }

    /**
     * @return array{impersonation_token: string, expires_at: string, audit_note: string}
     */
    public function impersonate(Company $company, User $admin): array
    {
        $partner = $company->users()->firstOrFail();
        $token = $partner->createToken('impersonation', ['*'], now()->addMinutes(15))->plainTextToken;

        return [
            'impersonation_token' => $token,
            'expires_at' => now()->addMinutes(15)->toIso8601String(),
            'audit_note' => sprintf(
                'Impersonation stub: admin %s (%s) issued token for company %s at %s',
                $admin->email,
                $admin->uuid,
                $company->uuid,
                now()->toIso8601String(),
            ),
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
    public function assignPartner(Lead $lead, int $companyId, User $admin): array
    {
        return DB::transaction(function () use ($lead, $companyId, $admin): array {
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

            return ['lead' => $lead->fresh(), 'assignment' => $match];
        });
    }

    public function reroute(Lead $lead): array
    {
        ProcessLeadMatching::dispatch($lead->id);

        return ['job_id' => (string) Str::ulid()];
    }

    /**
     * @return array{summary: array<string, int>, transactions: list<array<string, mixed>>}
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
                'today' => (int) Transaction::query()->whereDate('created_at', today())->count(),
                'week' => (int) Transaction::query()->where('created_at', '>=', now()->subWeek())->count(),
                'month' => (int) Transaction::query()->where('created_at', '>=', now()->subMonth())->count(),
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
        return [
            'total_aum' => Company::query()->where('vetting_status', VettingStatus::Approved)->count() * 10000,
            'revenue_under_management' => (int) Transaction::query()->sum('amount_cents') / 100,
            'monthly_growth' => 12.5,
            'active_contracts' => Company::query()->where('vetting_status', VettingStatus::Approved)->count(),
        ];
    }

    /**
     * @return array{by_sector: list<array>, by_region: list<array>, by_tier: list<array>}
     */
    public function portfolioAllocation(): array
    {
        return [
            'by_sector' => [['label' => 'Senior Care', 'value' => 100]],
            'by_region' => [['label' => 'Nord', 'value' => 60], ['label' => 'Centro', 'value' => 40]],
            'by_tier' => [['label' => 'Standard', 'value' => 80], ['label' => 'Premium', 'value' => 20]],
        ];
    }

    /**
     * @return array{partners: list<array<string, mixed>>}
     */
    public function portfolioPartners(): array
    {
        $partners = Company::query()
            ->where('vetting_status', VettingStatus::Approved)
            ->limit(20)
            ->get()
            ->map(fn (Company $c) => [
                'id' => $c->uuid,
                'nome' => $c->organization_name,
                'tier' => $c->tier?->value ?? 'standard',
                'aum' => 10000,
                'revenue_share' => 15,
                'trend' => [],
                'risk' => 'low',
            ])
            ->all();

        return ['partners' => $partners];
    }

    /**
     * @return array{indicators: list<array<string, mixed>>}
     */
    public function riskIndicators(): array
    {
        return [
            'indicators' => [
                ['label' => 'Partner sospesi', 'value' => Company::query()->where('vetting_status', VettingStatus::Suspended)->count(), 'severity' => 'medium'],
                ['label' => 'Lead in routing', 'value' => Lead::query()->where('status', LeadStatus::Processing)->count(), 'severity' => 'low'],
            ],
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
