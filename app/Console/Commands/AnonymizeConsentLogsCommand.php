<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ConsentLogRetentionService;
use Illuminate\Console\Command;

class AnonymizeConsentLogsCommand extends Command
{
    protected $signature = 'consent-logs:anonymize-retention {--dry-run : Count rows without updating}';

    protected $description = 'Anonymize IP, user agent, and session_id on consent_logs past retention period';

    public function handle(ConsentLogRetentionService $retentionService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $years = (int) config('wenando.consent_log_retention_years', 5);
        $cutoff = $retentionService->retentionCutoff();

        $count = $retentionService->anonymizeExpired($dryRun);

        if ($dryRun) {
            $this->components->info(sprintf(
                'Would anonymize %d consent log(s) older than %d year(s) (before %s).',
                $count,
                $years,
                $cutoff->toDateString(),
            ));
        } else {
            $this->components->info(sprintf(
                'Anonymized %d consent log(s) older than %d year(s) (before %s).',
                $count,
                $years,
                $cutoff->toDateString(),
            ));
        }

        return self::SUCCESS;
    }
}
