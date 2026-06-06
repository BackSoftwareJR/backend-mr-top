<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\EditorialIndexQueueAction;
use App\Enums\EditorialIndexQueueStatus;
use App\Models\EditorialIndexQueue;
use App\Services\Editorial\EditorialSearchIndexer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class IndexEditorialDocumentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $queueId,
    ) {}

    public function handle(EditorialSearchIndexer $indexer): void
    {
        /** @var EditorialIndexQueue|null $entry */
        $entry = EditorialIndexQueue::query()->find($this->queueId);

        if ($entry === null) {
            return;
        }

        $entry->update(['status' => EditorialIndexQueueStatus::Processing]);

        try {
            $content = $entry->editorialContent;

            if ($content === null) {
                throw new \RuntimeException('Contenuto editoriale non trovato per la coda di indicizzazione.');
            }

            match ($entry->action) {
                EditorialIndexQueueAction::Index,
                EditorialIndexQueueAction::Reindex => $indexer->index($content->load(['rubric'])),
                EditorialIndexQueueAction::Remove => $indexer->remove($content->uuid),
            };

            $entry->update([
                'status' => EditorialIndexQueueStatus::Completed,
                'processed_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $entry->update([
                'status' => EditorialIndexQueueStatus::Failed,
                'processed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
