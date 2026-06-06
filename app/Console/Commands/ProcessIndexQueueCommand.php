<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EditorialIndexQueueStatus;
use App\Jobs\IndexEditorialDocumentJob;
use App\Models\EditorialIndexQueue;
use Illuminate\Console\Command;

class ProcessIndexQueueCommand extends Command
{
    protected $signature = 'editorial:process-index-queue {--limit=50 : Maximum pending rows to process}';

    protected $description = 'Process pending editorial index queue jobs synchronously';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $entries = EditorialIndexQueue::query()
            ->where('status', EditorialIndexQueueStatus::Pending)
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($entries->isEmpty()) {
            $this->info('No pending editorial index queue entries.');

            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            try {
                IndexEditorialDocumentJob::dispatchSync($entry->id);
                $processed++;
            } catch (\Throwable $exception) {
                $failed++;
                $this->error(sprintf('Queue #%d failed: %s', $entry->id, $exception->getMessage()));
            }
        }

        $this->info(sprintf('Processed %d entries (%d failed).', $processed, $failed));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
