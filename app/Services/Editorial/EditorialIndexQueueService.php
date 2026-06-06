<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialContentStatus;
use App\Enums\EditorialIndexQueueAction;
use App\Enums\EditorialIndexQueueStatus;
use App\Jobs\IndexEditorialDocumentJob;
use App\Models\EditorialContent;
use App\Models\EditorialIndexQueue;
use Illuminate\Support\Collection;

class EditorialIndexQueueService
{
    public function enqueue(EditorialContent $content, EditorialIndexQueueAction $action): EditorialIndexQueue
    {
        return EditorialIndexQueue::query()->create([
            'editorial_content_id' => $content->id,
            'action' => $action,
            'status' => EditorialIndexQueueStatus::Pending,
            'scheduled_at' => now(),
        ]);
    }

    public function enqueueAndDispatch(EditorialContent $content, EditorialIndexQueueAction $action): EditorialIndexQueue
    {
        $entry = $this->enqueue($content, $action);
        IndexEditorialDocumentJob::dispatch($entry->id);

        return $entry;
    }

    /**
     * @return Collection<int, EditorialIndexQueue>
     */
    public function enqueueReindexForContent(EditorialContent $content): Collection
    {
        return collect([$this->enqueueAndDispatch($content, EditorialIndexQueueAction::Reindex)]);
    }

    /**
     * @return Collection<int, EditorialIndexQueue>
     */
    public function enqueueReindexForRubric(string $rubricSlug): Collection
    {
        $contents = EditorialContent::query()
            ->published()
            ->where('rubric_slug', $rubricSlug)
            ->get();

        return $contents->map(
            fn (EditorialContent $content): EditorialIndexQueue => $this->enqueueAndDispatch(
                $content,
                EditorialIndexQueueAction::Reindex,
            ),
        );
    }

    /**
     * @return Collection<int, EditorialIndexQueue>
     */
    public function enqueueReindexAllPublished(): Collection
    {
        $contents = EditorialContent::query()
            ->where('status', EditorialContentStatus::Published)
            ->get();

        return $contents->map(
            fn (EditorialContent $content): EditorialIndexQueue => $this->enqueueAndDispatch(
                $content,
                EditorialIndexQueueAction::Reindex,
            ),
        );
    }
}
