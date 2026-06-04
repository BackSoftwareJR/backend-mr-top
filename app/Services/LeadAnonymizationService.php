<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LeadAnonymizationService
{
    private const ANONYMIZED_EMAIL_DOMAIN = 'anonymized.wenando.local';

    private const ANONYMIZED_NEED_SUMMARY = 'Richiesta anonimizzata';

    private const ANONYMIZED_TITLE = 'Ricerca anonimizzata';

    /**
     * @var list<LeadStatus>
     */
    private const STALE_STATUSES = [
        LeadStatus::Closed,
        LeadStatus::Cancelled,
        LeadStatus::Routed,
        LeadStatus::Assigned,
    ];

    public function retentionCutoff(?CarbonInterface $now = null): CarbonInterface
    {
        $days = (int) config('wenando.lead_anonymize_days', 730);

        return ($now ?? now())->copy()->subDays($days);
    }

    /**
     * @return Builder<Lead>
     */
    public function staleLeadQuery(?CarbonInterface $now = null): Builder
    {
        $cutoff = $this->retentionCutoff($now);

        return Lead::query()
            ->whereNull('deleted_at')
            ->whereIn('status', array_map(
                static fn (LeadStatus $status): string => $status->value,
                self::STALE_STATUSES,
            ))
            ->where('updated_at', '<', $cutoff)
            ->where(function (Builder $builder): void {
                $builder->whereNull('contact_email')
                    ->orWhere(function (Builder $inner): void {
                        $inner->whereNotNull('contact_email')
                            ->where('contact_email', 'not like', 'anon_%');
                    });
            });
    }

    public function isAnonymized(Lead $lead): bool
    {
        $email = $lead->contact_email;

        return $email !== null
            && str_starts_with($email, 'anon_')
            && str_ends_with($email, '@'.self::ANONYMIZED_EMAIL_DOMAIN);
    }

    public function anonymizeStale(bool $dryRun = false, ?CarbonInterface $now = null): int
    {
        $query = $this->staleLeadQuery($now);

        if ($dryRun) {
            return $query->count();
        }

        return DB::transaction(function () use ($query): int {
            $affected = 0;

            $query->orderBy('id')->chunkById(200, function ($leads) use (&$affected): void {
                foreach ($leads as $lead) {
                    if ($this->isAnonymized($lead)) {
                        continue;
                    }

                    $this->anonymizeLead($lead);
                    $affected++;
                }
            });

            return $affected;
        });
    }

    public function anonymizeOwnedByUser(User $user): int
    {
        $affected = 0;

        Lead::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(100, function ($leads) use (&$affected): void {
                foreach ($leads as $lead) {
                    if ($this->isAnonymized($lead)) {
                        continue;
                    }

                    $this->anonymizeLead($lead, unlinkUser: true);
                    $affected++;
                }
            });

        return $affected;
    }

    public function anonymizeLead(Lead $lead, bool $unlinkUser = false): void
    {
        $attributes = [
            'contact_name' => null,
            'contact_phone' => null,
            'contact_email' => $this->anonymizedEmail($lead),
            'location_label' => $this->redactedLocationLabel($lead->location_label),
            'need_summary' => self::ANONYMIZED_NEED_SUMMARY,
            'title' => self::ANONYMIZED_TITLE,
            'payload' => $this->stripPayloadPii($lead->payload),
        ];

        if ($unlinkUser) {
            $attributes['user_id'] = null;
        }

        $lead->update($attributes);
    }

    private function anonymizedEmail(Lead $lead): ?string
    {
        $original = $lead->contact_email;

        if ($original === null || $original === '') {
            return null;
        }

        $prefix = substr(hash('sha256', strtolower($original).'|'.$lead->id), 0, 16);

        return 'anon_'.$prefix.'@'.self::ANONYMIZED_EMAIL_DOMAIN;
    }

    private function redactedLocationLabel(?string $label): ?string
    {
        if ($label === null || $label === '') {
            return null;
        }

        $city = trim(explode('(', $label, 2)[0]);
        $city = trim(explode(',', $city, 2)[0]);

        return $city !== '' ? $city : null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function stripPayloadPii(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        unset($payload['contact']);

        if (isset($payload['location']) && is_array($payload['location'])) {
            $payload['location'] = array_intersect_key(
                $payload['location'],
                array_flip(['value']),
            );
        }

        return $payload;
    }
}
