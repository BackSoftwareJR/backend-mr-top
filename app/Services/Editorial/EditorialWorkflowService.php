<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialContentStatus;
use App\Enums\EditorialIndexQueueAction;
use App\Enums\EditorialModerationStatus;
use App\Exceptions\ApiException;
use App\Jobs\GenerateEditorialSeoJob;
use App\Jobs\SuggestInternalLinksJob;
use App\Models\EditorialContent;
use App\Models\EditorialModerationQueue;
use App\Models\EditorialWorkflowEvent;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class EditorialWorkflowService
{
    public function __construct(
        private readonly EditorialSeoService $seoService,
        private readonly EditorialIndexQueueService $indexQueueService,
    ) {}
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'draft' => ['pending_review', 'published'],
        'pending_review' => ['published', 'rejected', 'draft'],
        'published' => ['archived'],
        'scheduled' => ['published'],
        'rejected' => ['draft'],
        'archived' => [],
    ];

    public function transition(
        EditorialContent $content,
        EditorialContentStatus $toStatus,
        User $actor,
        ?string $note = null,
    ): EditorialContent {
        $fromStatus = $content->status;

        if ($fromStatus === null) {
            throw new ApiException('INVALID_TRANSITION', 'Stato contenuto non valido.', 422);
        }

        if (! $this->isAllowedTransition($fromStatus, $toStatus)) {
            throw new ApiException(
                'INVALID_TRANSITION',
                sprintf(
                    'Transizione non consentita da %s a %s.',
                    $fromStatus->value,
                    $toStatus->value,
                ),
                422,
                [
                    'from_status' => $fromStatus->value,
                    'to_status' => $toStatus->value,
                ],
            );
        }

        if ($toStatus === EditorialContentStatus::Published) {
            $this->seoService->assertCanPublish($content->load('seoGenerations'), $actor);
        }

        return DB::transaction(function () use ($content, $fromStatus, $toStatus, $actor, $note): EditorialContent {
            $this->applyStatusSideEffects($content, $fromStatus, $toStatus, $actor);

            $content->status = $toStatus;
            $content->save();

            EditorialWorkflowEvent::query()->create([
                'content_id' => $content->id,
                'actor_user_id' => $actor->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $note,
            ]);

            $fresh = $content->fresh(['rubric', 'moderationQueueEntry', 'company']);

            if ($toStatus === EditorialContentStatus::Published) {
                $this->regeneratePublicFeeds();
                $this->indexQueueService->enqueueAndDispatch($fresh, EditorialIndexQueueAction::Index);
                SuggestInternalLinksJob::dispatch($fresh->id);
            }

            if ($toStatus === EditorialContentStatus::Archived) {
                $this->indexQueueService->enqueueAndDispatch($fresh, EditorialIndexQueueAction::Remove);
            }

            return $fresh;
        });
    }

    public function isAllowedTransition(
        EditorialContentStatus $fromStatus,
        EditorialContentStatus $toStatus,
    ): bool {
        $allowed = self::ALLOWED_TRANSITIONS[$fromStatus->value] ?? [];

        return in_array($toStatus->value, $allowed, true);
    }

    /**
     * @return array<string, list<string>>
     */
    public function transitionMatrix(): array
    {
        return self::ALLOWED_TRANSITIONS;
    }

    private function applyStatusSideEffects(
        EditorialContent $content,
        EditorialContentStatus $fromStatus,
        EditorialContentStatus $toStatus,
        User $actor,
    ): void {
        if ($toStatus === EditorialContentStatus::Published) {
            $content->published_at = now();
            $content->published_by_user_id = $actor->id;
            $content->unpublished_at = null;

            if ($fromStatus === EditorialContentStatus::PendingReview) {
                $content->reviewed_at = now();
                $content->reviewed_by_user_id = $actor->id;
            }

            $this->resolveModerationQueue($content, EditorialModerationStatus::Approved, $actor);

            return;
        }

        if ($toStatus === EditorialContentStatus::Archived) {
            $content->unpublished_at = now();

            return;
        }

        if ($toStatus === EditorialContentStatus::PendingReview) {
            $this->upsertModerationQueue($content, $actor, EditorialModerationStatus::Pending);
            GenerateEditorialSeoJob::dispatch($content->id);

            return;
        }

        if ($toStatus === EditorialContentStatus::Rejected) {
            $this->resolveModerationQueue($content, EditorialModerationStatus::Rejected, $actor);

            return;
        }

        if ($toStatus === EditorialContentStatus::Draft && $fromStatus === EditorialContentStatus::PendingReview) {
            $this->resolveModerationQueue($content, EditorialModerationStatus::RevisionRequested, $actor);
        }
    }

    private function upsertModerationQueue(
        EditorialContent $content,
        User $actor,
        EditorialModerationStatus $status,
    ): void {
        if ($content->company_id === null) {
            return;
        }

        /** @var EditorialModerationQueue|null $entry */
        $entry = $content->moderationQueueEntry;

        if ($entry === null) {
            EditorialModerationQueue::query()->create([
                'content_id' => $content->id,
                'company_id' => $content->company_id,
                'status' => $status,
                'submitted_by_user_id' => $actor->id,
                'submitted_at' => now(),
            ]);

            return;
        }

        $entry->update([
            'status' => $status,
            'submitted_by_user_id' => $actor->id,
            'submitted_at' => now(),
            'resolved_at' => null,
        ]);
    }

    private function resolveModerationQueue(
        EditorialContent $content,
        EditorialModerationStatus $status,
        User $actor,
    ): void {
        if ($content->company_id === null) {
            return;
        }

        /** @var EditorialModerationQueue|null $entry */
        $entry = $content->moderationQueueEntry;

        if ($entry === null) {
            return;
        }

        $entry->update([
            'status' => $status,
            'assigned_reviewer_id' => $actor->id,
            'resolved_at' => now(),
        ]);
    }

    private function regeneratePublicFeeds(): void
    {
        Artisan::call('editorial:generate-sitemaps');
        Artisan::call('editorial:generate-llms-txt');
    }
}
