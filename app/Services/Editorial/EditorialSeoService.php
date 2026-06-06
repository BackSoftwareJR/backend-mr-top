<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialSeoGenerationStatus;
use App\Enums\UserType;
use App\Exceptions\ApiException;
use App\Jobs\GenerateEditorialSeoJob;
use App\Models\EditorialContent;
use App\Models\EditorialContentSeoAudit;
use App\Models\EditorialSeoGeneration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EditorialSeoService
{
    public function __construct(
        private readonly EditorialSeoGroqService $groqService,
    ) {}

    public function dispatchGeneration(EditorialContent $content): void
    {
        GenerateEditorialSeoJob::dispatch($content->id);
    }

    public function regenerate(EditorialContent $content): EditorialSeoGeneration
    {
        return $this->groqService->generateAndStore($content);
    }

    public function approve(EditorialContent $content, User $actor, ?int $generationId = null): EditorialContent
    {
        $generation = $this->resolveGeneration($content, $generationId);

        if ($generation === null) {
            throw new ApiException('SEO_GENERATION_NOT_FOUND', 'Nessuna generazione SEO disponibile.', 422);
        }

        if ($generation->status !== EditorialSeoGenerationStatus::Pending) {
            throw new ApiException('SEO_ALREADY_REVIEWED', 'La generazione SEO è già stata revisionata.', 422);
        }

        $minScore = (int) config('editorial.seo.min_score');
        $score = (int) ($generation->score ?? 0);

        if ($score < $minScore) {
            throw new ApiException(
                'SEO_SCORE_TOO_LOW',
                sprintf('Il punteggio SEO (%d) è inferiore al minimo richiesto (%d).', $score, $minScore),
                422,
                ['seo_score' => $score, 'min_score' => $minScore],
            );
        }

        return DB::transaction(function () use ($content, $generation, $actor): EditorialContent {
            $seoPack = $generation->seo_pack ?? [];
            $seoPack['approved'] = true;
            $seoPack['approved_by_user_id'] = $actor->id;
            $seoPack['approved_at'] = now()->toIso8601String();

            $content->seo_pack = $seoPack;

            if (! empty($seoPack['excerpt'])) {
                $content->excerpt = (string) $seoPack['excerpt'];
            }

            if (! empty($seoPack['suggested_tags']) && is_array($seoPack['suggested_tags'])) {
                $content->tags = array_values($seoPack['suggested_tags']);
            }

            $content->save();

            $generation->update([
                'status' => EditorialSeoGenerationStatus::Approved,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
            ]);

            EditorialContentSeoAudit::query()->create([
                'editorial_content_id' => $content->id,
                'revision_number' => $content->revisions()->count(),
                'seo_pack' => $seoPack,
                'seo_score' => $generation->score,
                'approved' => true,
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
            ]);

            return $content->fresh(['rubric', 'seoGenerations']);
        });
    }

    public function reject(EditorialContent $content, User $actor, ?string $note = null, ?int $generationId = null): EditorialSeoGeneration
    {
        $generation = $this->resolveGeneration($content, $generationId);

        if ($generation === null) {
            throw new ApiException('SEO_GENERATION_NOT_FOUND', 'Nessuna generazione SEO disponibile.', 422);
        }

        if ($generation->status !== EditorialSeoGenerationStatus::Pending) {
            throw new ApiException('SEO_ALREADY_REVIEWED', 'La generazione SEO è già stata revisionata.', 422);
        }

        $generation->update([
            'status' => EditorialSeoGenerationStatus::Rejected,
            'reviewed_by_user_id' => $actor->id,
            'reviewed_at' => now(),
            'error_message' => $note,
        ]);

        return $generation->fresh();
    }

    /**
     * @return array{latest: ?EditorialSeoGeneration, history: list<EditorialSeoGeneration>}
     */
    public function history(EditorialContent $content): array
    {
        $generations = $content->seoGenerations()->get();

        return [
            'latest' => $generations->first(),
            'history' => $generations->all(),
        ];
    }

    public function assertCanPublish(EditorialContent $content, User $actor): void
    {
        if (! (bool) config('editorial.seo.require_seo_approval')) {
            return;
        }

        if ((bool) config('editorial.seo.superadmin_bypass') && $actor->user_type === UserType::Superadmin) {
            return;
        }

        if ($this->hasManualSeo($content)) {
            return;
        }

        /** @var EditorialSeoGeneration|null $latest */
        $latest = $content->seoGenerations()->first();

        if ($latest === null || $latest->status !== EditorialSeoGenerationStatus::Approved) {
            throw new ApiException(
                'SEO_NOT_APPROVED',
                'Impossibile pubblicare: SEO non approvato. Approva la generazione SEO o compila manualmente seo_title e seo_description.',
                422,
                [
                    'latest_generation_status' => $latest?->status?->value,
                    'min_score' => (int) config('editorial.seo.min_score'),
                ],
            );
        }

        $minScore = (int) config('editorial.seo.min_score');
        $score = (int) ($latest->score ?? 0);

        if ($score < $minScore) {
            throw new ApiException(
                'SEO_NOT_APPROVED',
                sprintf('Impossibile pubblicare: punteggio SEO (%d) inferiore al minimo (%d).', $score, $minScore),
                422,
                ['seo_score' => $score, 'min_score' => $minScore],
            );
        }
    }

    public function hasManualSeo(EditorialContent $content): bool
    {
        $seoPack = $content->seo_pack;

        if (! is_array($seoPack)) {
            return false;
        }

        $manualTitle = trim((string) (
            $seoPack['manual_overrides']['seo_title']
            ?? $seoPack['seo_title']
            ?? ''
        ));
        $manualDescription = trim((string) (
            $seoPack['manual_overrides']['meta_description']
            ?? $seoPack['manual_overrides']['seo_description']
            ?? $seoPack['seo_description']
            ?? $seoPack['meta_description']
            ?? ''
        ));

        return $manualTitle !== '' && $manualDescription !== '';
    }

    private function resolveGeneration(EditorialContent $content, ?int $generationId): ?EditorialSeoGeneration
    {
        if ($generationId !== null) {
            return EditorialSeoGeneration::query()
                ->where('content_id', $content->id)
                ->whereKey($generationId)
                ->first();
        }

        return $content->seoGenerations()->first();
    }
}
