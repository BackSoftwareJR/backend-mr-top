<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CrmStatus;
use App\Models\ActivityFeed;
use App\Models\Company;
use App\Models\LeadMatch;
use App\Models\Transaction;

class ActivityFeedService
{
    public function recordWalletRecharge(Company $company, Transaction $transaction): void
    {
        $credits = abs($transaction->credits_delta);

        $this->record(
            $company,
            'recharge',
            sprintf('Ricarica wallet: %d crediti', $credits),
            [
                'transaction_id' => $transaction->id,
                'public_ref' => $transaction->public_ref,
                'credits' => $credits,
            ],
        );
    }

    public function recordLeadUnlock(Company $company, LeadMatch $leadMatch, ?Transaction $transaction = null): void
    {
        $leadMatch->loadMissing('lead');
        $contactName = $leadMatch->lead?->contact_name;

        $text = $contactName !== null
            ? sprintf('Lead sbloccato: %s', $contactName)
            : ($transaction?->description ?? 'Lead sbloccato');

        $metadata = [
            'lead_match_id' => $leadMatch->id,
            'contact_name' => $contactName,
        ];

        if ($transaction !== null) {
            $metadata['transaction_id'] = $transaction->id;
            $metadata['public_ref'] = $transaction->public_ref;
        }

        $this->record($company, 'unlock', $text, $metadata);
    }

    public function recordCrmStatusChange(
        Company $company,
        LeadMatch $leadMatch,
        ?CrmStatus $from,
        CrmStatus $to,
    ): void {
        $leadMatch->loadMissing('lead');
        $contactName = $leadMatch->lead?->contact_name ?? 'Lead';

        $this->record(
            $company,
            'status',
            sprintf(
                'Stato aggiornato: %s → %s',
                $contactName,
                $this->crmStatusLabel($to),
            ),
            [
                'lead_match_id' => $leadMatch->id,
                'old_status' => $from?->value,
                'new_status' => $to->value,
                'contact_name' => $contactName,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function record(Company $company, string $type, string $text, array $metadata = []): void
    {
        ActivityFeed::query()->create([
            'company_id' => $company->id,
            'type' => $type,
            'text' => $text,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function crmStatusLabel(CrmStatus $status): string
    {
        return match ($status) {
            CrmStatus::Nuovo => 'Nuovo',
            CrmStatus::Contattato => 'Contattato',
            CrmStatus::VisitaFissata => 'Visita Fissata',
            CrmStatus::Perso => 'Perso',
            CrmStatus::Chiuso => 'Chiuso',
        };
    }
}
