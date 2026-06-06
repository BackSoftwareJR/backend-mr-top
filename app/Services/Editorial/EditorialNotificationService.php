<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialContentStatus;
use App\Enums\EditorialModerationStatus;
use App\Enums\EditorialNotificationType;
use App\Enums\EditorialSeoGenerationStatus;
use App\Mail\EditorialPendingReviewMail;
use App\Mail\EditorialReviewDigestMail;
use App\Mail\EditorialReviewOutcomeMail;
use App\Mail\EditorialSeoReviewMail;
use App\Models\Company;
use App\Models\EditorialContent;
use App\Models\EditorialModerationQueue;
use App\Models\EditorialSeoGeneration;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class EditorialNotificationService
{
    public function handleWorkflowTransition(
        EditorialContent $content,
        EditorialContentStatus $fromStatus,
        EditorialContentStatus $toStatus,
        User $actor,
        ?string $note,
        int $workflowEventId,
    ): void {
        if ($toStatus === EditorialContentStatus::PendingReview && $content->company_id !== null) {
            $this->notifyPendingReview($content, $actor, $workflowEventId);

            return;
        }

        if (
            $fromStatus === EditorialContentStatus::PendingReview
            && $content->company_id !== null
            && in_array($toStatus, [EditorialContentStatus::Published, EditorialContentStatus::Rejected], true)
        ) {
            $this->notifyReviewOutcome($content, $toStatus, $note, $workflowEventId);
        }
    }

    public function notifyPendingReview(EditorialContent $content, User $actor, int $workflowEventId): void
    {
        $dedupeKey = "workflow:{$workflowEventId}:pending_review";

        if ($this->alreadyNotified($dedupeKey)) {
            return;
        }

        $content->loadMissing(['company']);
        $reviewers = $this->reviewers();

        if ($reviewers->isEmpty()) {
            return;
        }

        $payload = $this->buildPayload(
            EditorialNotificationType::PendingReview,
            $content,
            $dedupeKey,
            [
                'submitted_by_user_id' => $actor->id,
            ],
        );

        foreach ($reviewers as $reviewer) {
            $this->createInAppNotification($reviewer, EditorialNotificationType::PendingReview, $payload);

            if ($reviewer->email) {
                Mail::to($reviewer->email)->queue(new EditorialPendingReviewMail(
                    recipientName: $this->displayName($reviewer),
                    contentTitle: $content->title,
                    companyName: $content->company?->organization_name ?? 'Struttura partner',
                    reviewUrl: $this->adminReviewUrl(),
                ));
            }
        }
    }

    public function notifyReviewOutcome(
        EditorialContent $content,
        EditorialContentStatus $outcome,
        ?string $note,
        int $workflowEventId,
    ): void {
        $dedupeKey = "workflow:{$workflowEventId}:review_outcome";

        if ($this->alreadyNotified($dedupeKey)) {
            return;
        }

        $content->loadMissing(['company']);
        $company = $content->company;

        if ($company === null) {
            return;
        }

        $outcomeLabel = $outcome === EditorialContentStatus::Published ? 'approved' : 'rejected';
        $payload = $this->buildPayload(
            EditorialNotificationType::ReviewOutcome,
            $content,
            $dedupeKey,
            [
                'outcome' => $outcomeLabel,
                'note' => $note !== null && $note !== '' ? $note : null,
            ],
        );

        $this->createInAppNotification($company, EditorialNotificationType::ReviewOutcome, $payload);

        $recipients = $this->companyUsers($company);

        foreach ($recipients as $recipient) {
            if (! $recipient->email) {
                continue;
            }

            Mail::to($recipient->email)->queue(new EditorialReviewOutcomeMail(
                recipientName: $this->displayName($recipient),
                contentTitle: $content->title,
                approved: $outcome === EditorialContentStatus::Published,
                note: $note,
                contentUrl: $this->b2bContentUrl($content),
            ));
        }
    }

    public function notifySeoNeedsReview(
        EditorialContent $content,
        EditorialSeoGeneration $generation,
        string $reason,
    ): void {
        $dedupeKey = "seo:{$generation->id}:{$reason}";

        if ($this->alreadyNotified($dedupeKey)) {
            return;
        }

        $content->loadMissing(['company', 'moderationQueueEntry']);
        $score = (int) ($generation->score ?? 0);
        $minScore = (int) config('editorial.seo.min_score');

        $payload = $this->buildPayload(
            EditorialNotificationType::SeoNeedsReview,
            $content,
            $dedupeKey,
            [
                'generation_id' => $generation->id,
                'seo_score' => $score,
                'min_score' => $minScore,
                'reason' => $reason,
            ],
        );

        $recipients = $this->reviewers()->merge($this->resolveContentAuthors($content))->unique('id');

        foreach ($recipients as $recipient) {
            $this->createInAppNotification($recipient, EditorialNotificationType::SeoNeedsReview, $payload);

            if (! $recipient->email) {
                continue;
            }

            Mail::to($recipient->email)->queue(new EditorialSeoReviewMail(
                recipientName: $this->displayName($recipient),
                contentTitle: $content->title,
                seoScore: $score,
                minScore: $minScore,
                reason: $reason,
                editUrl: $this->adminContentEditUrl($content),
            ));
        }
    }

    /**
     * @return array{pending_moderation: int, seo_attention: int, items: list<array<string, mixed>>}
     */
    public function buildDigestSummary(): array
    {
        $pendingCount = EditorialModerationQueue::query()
            ->where('status', EditorialModerationStatus::Pending)
            ->count();

        $minScore = (int) config('editorial.seo.min_score');

        $seoItems = EditorialSeoGeneration::query()
            ->with(['content:id,uuid,title,status'])
            ->where('status', EditorialSeoGenerationStatus::Pending)
            ->where(function ($query) use ($minScore): void {
                $query->where('score', '<', $minScore)
                    ->orWhereNotNull('error_message');
            })
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(static fn (EditorialSeoGeneration $generation): array => [
                'content_uuid' => $generation->content?->uuid,
                'title' => $generation->content?->title ?? 'Contenuto',
                'seo_score' => (int) ($generation->score ?? 0),
                'reason' => $generation->error_message ? 'rejected' : 'low_score',
            ])
            ->values()
            ->all();

        return [
            'pending_moderation' => $pendingCount,
            'seo_attention' => count($seoItems),
            'items' => $seoItems,
        ];
    }

    public function sendReviewDigest(): int
    {
        $summary = $this->buildDigestSummary();

        if ($summary['pending_moderation'] === 0 && $summary['seo_attention'] === 0) {
            return 0;
        }

        $reviewers = $this->reviewers();
        $sent = 0;

        foreach ($reviewers as $reviewer) {
            if (! $reviewer->email) {
                continue;
            }

            Mail::to($reviewer->email)->queue(new EditorialReviewDigestMail(
                recipientName: $this->displayName($reviewer),
                pendingModeration: $summary['pending_moderation'],
                seoAttention: $summary['seo_attention'],
                reviewUrl: $this->adminReviewUrl(),
            ));
            $sent++;
        }

        return $sent;
    }

    /**
     * @return Collection<int, User>
     */
    public function reviewers(): Collection
    {
        return User::query()
            ->whereNotNull('email')
            ->whereHas('roles', static fn ($query) => $query->whereIn('name', ['reviewer', 'chief_editor']))
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function companyUsers(Company $company): Collection
    {
        return $company->users()
            ->whereNotNull('email')
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveContentAuthors(EditorialContent $content): Collection
    {
        $users = collect();

        $submittedBy = $content->moderationQueueEntry?->submitted_by_user_id;

        if ($submittedBy !== null) {
            $user = User::query()->find($submittedBy);

            if ($user !== null) {
                $users->push($user);
            }
        }

        if ($content->company_id !== null && $content->company !== null) {
            $users = $users->merge($this->companyUsers($content->company));
        }

        return $users->unique('id')->values();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function buildPayload(
        EditorialNotificationType $type,
        EditorialContent $content,
        string $dedupeKey,
        array $extra = [],
    ): array {
        return array_merge([
            'dedupe_key' => $dedupeKey,
            'type' => $type->value,
            'message' => $type->label(),
            'content_uuid' => $content->uuid,
            'content_title' => $content->title,
            'content_status' => $content->status?->value,
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createInAppNotification(
        User|Company $notifiable,
        EditorialNotificationType $type,
        array $payload,
    ): void {
        Notification::query()->create([
            'type' => $type->value,
            'notifiable_type' => $notifiable::class,
            'notifiable_id' => $notifiable->getKey(),
            'data' => $payload,
            'read_at' => null,
        ]);
    }

    private function alreadyNotified(string $dedupeKey): bool
    {
        return Notification::query()
            ->where('data->dedupe_key', $dedupeKey)
            ->exists();
    }

    private function displayName(User $user): string
    {
        $name = trim((string) ($user->name ?? ''));

        return $name !== '' ? $name : 'Utente Wenando';
    }

    private function adminReviewUrl(): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/admin/editorial/review';
    }

    private function adminContentEditUrl(EditorialContent $content): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/admin/editorial/'.$content->uuid.'/edit';
    }

    private function b2bContentUrl(EditorialContent $content): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/pro/editoriale/'.$content->uuid.'/edit';
    }
}
