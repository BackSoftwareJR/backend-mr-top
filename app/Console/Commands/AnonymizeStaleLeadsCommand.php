<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LeadAnonymizationService;
use Illuminate\Console\Command;

class AnonymizeStaleLeadsCommand extends Command
{
    protected $signature = 'leads:anonymize-stale {--dry-run : Count rows without updating}';

    protected $description = 'Anonymize PII on stale leads past retention period';

    public function handle(LeadAnonymizationService $anonymizationService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = (int) config('wenando.lead_anonymize_days', 730);
        $cutoff = $anonymizationService->retentionCutoff();

        $count = $anonymizationService->anonymizeStale($dryRun);

        if ($dryRun) {
            $this->components->info(sprintf(
                'Would anonymize %d stale lead(s) older than %d day(s) (updated before %s).',
                $count,
                $days,
                $cutoff->toDateString(),
            ));
        } else {
            $this->components->info(sprintf(
                'Anonymized %d stale lead(s) older than %d day(s) (updated before %s).',
                $count,
                $days,
                $cutoff->toDateString(),
            ));
        }

        return self::SUCCESS;
    }
}
