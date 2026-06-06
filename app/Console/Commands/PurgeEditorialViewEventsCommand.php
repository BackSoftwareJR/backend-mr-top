<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EditorialViewEvent;
use Illuminate\Console\Command;

class PurgeEditorialViewEventsCommand extends Command
{
    protected $signature = 'editorial:purge-view-events {--days=90 : Retention window in days}';

    protected $description = 'Delete editorial view-event dedupe rows older than the retention window (GDPR).';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days)->toDateString();

        $deleted = EditorialViewEvent::query()
            ->where('date', '<', $cutoff)
            ->delete();

        $this->info("Purged {$deleted} editorial view events older than {$days} days.");

        return self::SUCCESS;
    }
}
