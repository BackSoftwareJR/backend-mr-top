<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AppointmentType;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\CompanyProfile;
use App\Models\LeadMatch;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * @return list<array{type: string, label: string, formats: list<string>}>
     */
    public function availableTypes(): array
    {
        return [
            ['type' => 'leads', 'label' => 'Lead sbloccati', 'formats' => ['csv', 'json']],
            ['type' => 'crm', 'label' => 'Clienti CRM', 'formats' => ['csv', 'json']],
            ['type' => 'transactions', 'label' => 'Transazioni wallet', 'formats' => ['csv', 'json']],
            ['type' => 'appointments', 'label' => 'Appuntamenti', 'formats' => ['csv', 'json']],
            ['type' => 'profile', 'label' => 'Profilo azienda', 'formats' => ['csv', 'json']],
        ];
    }

    /**
     * @return array{format: string, filename: string, content: string|StreamedResponse, row_count: int}
     */
    public function export(Company $company, string $type, string $format): array
    {
        $rows = match ($type) {
            'leads' => $this->exportLeads($company),
            'crm' => $this->exportCrm($company),
            'transactions' => $this->exportTransactions($company),
            'appointments' => $this->exportAppointments($company),
            'profile' => $this->exportProfile($company),
            default => throw new \InvalidArgumentException("Unknown export type: {$type}"),
        };

        $filename = sprintf('wenando-%s-%s-%s.%s', $type, $company->id, now()->format('Ymd-His'), $format);

        if ($format === 'json') {
            return [
                'format' => 'json',
                'filename' => $filename,
                'content' => json_encode(['rows' => $rows], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'row_count' => count($rows),
            ];
        }

        return [
            'format' => 'csv',
            'filename' => $filename,
            'content' => $this->toCsvStream($rows),
            'row_count' => count($rows),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportLeads(Company $company): array
    {
        return LeadMatch::query()
            ->where('company_id', $company->id)
            ->whereNotNull('unlocked_at')
            ->with('lead:id,title,contact_name,contact_email,contact_phone,city')
            ->orderByDesc('unlocked_at')
            ->get()
            ->map(fn (LeadMatch $match): array => [
                'public_ref' => $match->public_ref,
                'lead_title' => $match->lead?->title,
                'contact_name' => $match->lead?->contact_name,
                'contact_email' => $match->lead?->contact_email,
                'contact_phone' => $match->lead?->contact_phone,
                'city' => $match->lead?->city,
                'match_score' => $match->match_score,
                'unlocked_at' => $match->unlocked_at?->toIso8601String(),
                'unlock_cost_credits' => $match->unlock_cost_credits,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportCrm(Company $company): array
    {
        return LeadMatch::query()
            ->where('company_id', $company->id)
            ->whereNotNull('unlocked_at')
            ->with('lead:id,title,contact_name,contact_email,contact_phone,city')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (LeadMatch $match): array => [
                'public_ref' => $match->public_ref,
                'crm_status' => $match->crm_status?->value,
                'lead_title' => $match->lead?->title,
                'contact_name' => $match->lead?->contact_name,
                'contact_email' => $match->lead?->contact_email,
                'contact_phone' => $match->lead?->contact_phone,
                'city' => $match->lead?->city,
                'updated_at' => $match->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportTransactions(Company $company): array
    {
        return Transaction::query()
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Transaction $tx): array => [
                'public_ref' => $tx->public_ref,
                'type' => $tx->type?->value,
                'status' => $tx->status?->value,
                'amount_cents' => $tx->amount_cents,
                'credits_delta' => $tx->credits_delta,
                'payment_method' => $tx->payment_method?->value,
                'description' => $tx->description,
                'completed_at' => $tx->completed_at?->toIso8601String(),
                'created_at' => $tx->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportAppointments(Company $company): array
    {
        return Appointment::query()
            ->where('company_id', $company->id)
            ->where('type', AppointmentType::Visit)
            ->orderByDesc('scheduled_date')
            ->get()
            ->map(fn (Appointment $apt): array => [
                'id' => $apt->id,
                'client_name' => $apt->client_name,
                'scheduled_date' => $apt->scheduled_date?->toDateString(),
                'scheduled_time' => $apt->scheduled_time,
                'note' => $apt->note,
                'checklist_items' => is_array($apt->checklist) ? count($apt->checklist) : 0,
                'created_at' => $apt->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportProfile(Company $company): array
    {
        $profile = CompanyProfile::query()->where('company_id', $company->id)->first();

        return [[
            'organization_name' => $company->organization_name,
            'legal_name' => $company->legal_name,
            'vat_number' => $company->vat_number,
            'city' => $company->city,
            'email' => $company->email,
            'phone' => $company->phone,
            'website' => $profile?->website,
            'description' => $profile?->description,
            'service_areas' => $profile?->service_areas,
        ]];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function toCsvStream(array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            if ($rows === []) {
                fputcsv($handle, ['message'], ',', '"', '\\');
                fputcsv($handle, ['Nessun dato disponibile'], ',', '"', '\\');
                fclose($handle);

                return;
            }

            $headers = array_keys($rows[0]);
            fputcsv($handle, $headers, ',', '"', '\\');

            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $header) {
                    $value = $row[$header] ?? '';
                    $line[] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
                }
                fputcsv($handle, $line, ',', '"', '\\');
            }

            fclose($handle);
        }, 'export.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
