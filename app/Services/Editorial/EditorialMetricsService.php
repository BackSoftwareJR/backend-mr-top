<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialContentStatus;
use App\Enums\EditorialIndexQueueStatus;
use App\Enums\EditorialModerationStatus;
use App\Models\EditorialContent;
use App\Models\EditorialIndexQueue;
use App\Models\EditorialModerationQueue;
use App\Models\EditorialSeoGeneration;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class EditorialMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function aggregate(): array
    {
        return [
            'contents_by_status' => $this->contentsByStatus(),
            'moderation_backlog' => $this->moderationBacklog(),
            'seo_score_histogram' => $this->seoScoreHistogram(),
            'published_last_30_days' => $this->publishedLast30Days(),
            'index_queue_pending' => $this->indexQueuePending(),
            'top_published_by_type' => $this->topPublishedByType(),
            'searches_count' => $this->searchesCount(),
            'leads_with_email' => $this->leadsWithEmail(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function contentsByStatus(): array
    {
        $counts = EditorialContent::query()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = [];

        foreach (EditorialContentStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $byStatus;
    }

    private function moderationBacklog(): int
    {
        return EditorialModerationQueue::query()
            ->whereIn('status', [
                EditorialModerationStatus::Pending->value,
                EditorialModerationStatus::InReview->value,
            ])
            ->count();
    }

    /**
     * @return list<array{label: string, min: int, max: int, count: int}>
     */
    private function seoScoreHistogram(): array
    {
        $buckets = [
            ['label' => '0–49', 'min' => 0, 'max' => 49, 'count' => 0],
            ['label' => '50–69', 'min' => 50, 'max' => 69, 'count' => 0],
            ['label' => '70–79', 'min' => 70, 'max' => 79, 'count' => 0],
            ['label' => '80–89', 'min' => 80, 'max' => 89, 'count' => 0],
            ['label' => '90–100', 'min' => 90, 'max' => 100, 'count' => 0],
        ];

        $latestGenerationIds = EditorialSeoGeneration::query()
            ->selectRaw('MAX(id) as id')
            ->whereNotNull('score')
            ->groupBy('content_id');

        $scores = EditorialSeoGeneration::query()
            ->whereIn('id', $latestGenerationIds)
            ->pluck('score');

        foreach ($scores as $score) {
            $value = (int) $score;

            foreach ($buckets as &$bucket) {
                if ($value >= $bucket['min'] && $value <= $bucket['max']) {
                    $bucket['count']++;
                    break;
                }
            }
            unset($bucket);
        }

        return $buckets;
    }

    private function publishedLast30Days(): int
    {
        return EditorialContent::query()
            ->where('status', EditorialContentStatus::Published)
            ->where('published_at', '>=', now()->subDays(30))
            ->count();
    }

    private function indexQueuePending(): int
    {
        return EditorialIndexQueue::query()
            ->where('status', EditorialIndexQueueStatus::Pending)
            ->count();
    }

    /**
     * @return list<array{type: string, count: int}>
     */
    private function topPublishedByType(): array
    {
        return EditorialContent::query()
            ->where('status', EditorialContentStatus::Published)
            ->select('content_type', DB::raw('COUNT(*) as count'))
            ->groupBy('content_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row): array => [
                'type' => $row->content_type instanceof \App\Enums\EditorialContentType
                    ? $row->content_type->value
                    : (string) $row->content_type,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    private function searchesCount(): int
    {
        return Lead::query()->count();
    }

    private function leadsWithEmail(): int
    {
        return Lead::query()
            ->whereNotNull('contact_email')
            ->where('contact_email', '!=', '')
            ->count();
    }
}
