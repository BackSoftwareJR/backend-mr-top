<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConsentLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ConsentLogRetentionService
{
    public function retentionCutoff(?CarbonInterface $now = null): CarbonInterface
    {
        $years = (int) config('wenando.consent_log_retention_years', 5);

        return ($now ?? now())->copy()->subYears($years);
    }

    /**
     * Anonymize IP, user agent, and session_id on consent logs past retention.
     */
    public function anonymizeExpired(bool $dryRun = false, ?CarbonInterface $now = null): int
    {
        $cutoff = $this->retentionCutoff($now);

        $query = ConsentLog::query()
            ->where('created_at', '<', $cutoff)
            ->where(function ($builder): void {
                $builder->whereNotNull('ip_address')
                    ->orWhereNotNull('user_agent')
                    ->orWhereNotNull('session_id');
            });

        if ($dryRun) {
            return $query->count();
        }

        return DB::transaction(function () use ($query): int {
            $affected = 0;

            $query->orderBy('id')->chunkById(500, function ($logs) use (&$affected): void {
                foreach ($logs as $log) {
                    $log->update([
                        'ip_address' => null,
                        'user_agent' => null,
                        'session_id' => null,
                    ]);
                    $affected++;
                }
            });

            return $affected;
        });
    }
}
