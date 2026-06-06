<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentStatus;
use App\Exceptions\ApiException;
use App\Models\EditorialContent;
use App\Models\EditorialRubric;
use App\Models\EditorialWorkflowEvent;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgentEditorialIngestService
{
    public function __construct(
        private readonly EditorialWorkflowService $workflowService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{uuid: string, status: string, idempotent: bool}
     */
    public function ingest(array $payload): array
    {
        $externalRef = isset($payload['external_ref']) ? (string) $payload['external_ref'] : null;

        if ($externalRef !== null && $externalRef !== '') {
            $existing = EditorialContent::query()
                ->where('external_ref', $externalRef)
                ->first();

            if ($existing !== null) {
                return $this->responseFor($existing, true);
            }
        }

        try {
            $content = DB::transaction(function () use ($payload, $externalRef): EditorialContent {
                $content = $this->createDraft($payload, $externalRef);

                EditorialWorkflowEvent::query()->create([
                    'content_id' => $content->id,
                    'actor_user_id' => $this->resolveAgentActor()->id,
                    'from_status' => null,
                    'to_status' => EditorialContentStatus::Draft,
                    'note' => 'agent ingest',
                ]);

                if ((bool) ($payload['auto_submit_review'] ?? false)) {
                    $content = $this->workflowService->transition(
                        $content,
                        EditorialContentStatus::PendingReview,
                        $this->resolveAgentActor(),
                        'agent ingest — auto submit review',
                    );
                }

                return $content->fresh(['rubric']);
            });
        } catch (UniqueConstraintViolationException) {
            if ($externalRef === null || $externalRef === '') {
                throw new ApiException('INGEST_FAILED', 'Impossibile creare il contenuto agent.', 409);
            }

            $existing = EditorialContent::query()
                ->where('external_ref', $externalRef)
                ->firstOrFail();

            return $this->responseFor($existing, true);
        }

        return $this->responseFor($content, false);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createDraft(array $payload, ?string $externalRef): EditorialContent
    {
        $rubric = EditorialRubric::query()
            ->where('slug', (string) $payload['rubric_slug'])
            ->firstOrFail();

        $title = (string) $payload['title'];
        $bodyBlocks = $payload['body_blocks'];

        return EditorialContent::query()->create([
            'external_ref' => $externalRef !== '' ? $externalRef : null,
            'slug' => $this->uniqueSlug($title),
            'content_type' => $payload['type'],
            'status' => EditorialContentStatus::Draft,
            'title' => $title,
            'subtitle' => $payload['subtitle'] ?? null,
            'body_blocks' => $bodyBlocks,
            'rubric_id' => $rubric->id,
            'rubric_slug' => $rubric->slug,
            'sector_id' => Sector::query()->where('is_active', true)->value('id'),
            'author_type' => EditorialAuthorType::Agent,
            'locale' => 'it-IT',
            ...$this->metricsFromBlocks($bodyBlocks),
        ]);
    }

    private function resolveAgentActor(): User
    {
        $email = config('editorial.agent_user_email');

        if (! is_string($email) || $email === '') {
            throw new ApiException(
                'AGENT_USER_NOT_CONFIGURED',
                'Utente di sistema agent non configurato.',
                503,
            );
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw new ApiException(
                'AGENT_USER_NOT_CONFIGURED',
                'Utente di sistema agent non configurato.',
                503,
            );
        }

        return $user;
    }

    /**
     * @return array{uuid: string, status: string, idempotent: bool}
     */
    private function responseFor(EditorialContent $content, bool $idempotent): array
    {
        return [
            'uuid' => $content->uuid,
            'status' => $content->status?->value ?? EditorialContentStatus::Draft->value,
            'idempotent' => $idempotent,
        ];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 80, ''));
        if ($base === '') {
            $base = 'contenuto';
        }

        $slug = $base;
        $suffix = 1;

        while (EditorialContent::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array{word_count: int, read_minutes: int}
     */
    private function metricsFromBlocks(array $blocks): array
    {
        $text = collect($blocks)
            ->map(function (array $block): string {
                $data = $block['data'] ?? [];

                return match ($block['type'] ?? '') {
                    'heading' => (string) ($data['text'] ?? ''),
                    'paragraph' => strip_tags((string) ($data['html'] ?? '')),
                    'callout' => strip_tags((string) ($data['body'] ?? $data['html'] ?? '')),
                    default => '',
                };
            })
            ->implode(' ');

        $wordCount = str_word_count($text);

        return [
            'word_count' => $wordCount,
            'read_minutes' => max(1, (int) ceil($wordCount / 200)),
        ];
    }
}
