<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Models\EditorialContent;
use App\Models\EditorialContentDailyStat;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EditorialAnalyticsService
{
    /**
     * @return array{
     *     views_by_day: list<array{date: string, views: int, uniques: int, bot_views: int}>,
     *     totals: array{page_views: int, unique_visitors: int, bot_views: int},
     *     top_articles: list<array<string, mixed>>,
     * }
     */
    public function getContentStats(int $contentId, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->dailyRows($from, $to, $contentId);

        return [
            'views_by_day' => $this->formatViewsByDay($rows, $from, $to),
            'totals' => $this->sumTotals($rows),
            'top_articles' => [],
        ];
    }

    /**
     * @return array{
     *     views_by_day: list<array{date: string, views: int, uniques: int, bot_views: int}>,
     *     totals: array{page_views: int, unique_visitors: int, bot_views: int},
     *     top_articles: list<array<string, mixed>>,
     * }
     */
    public function getCompanyStats(int $companyId, CarbonInterface $from, CarbonInterface $to): array
    {
        $contentIds = EditorialContent::query()
            ->where('company_id', $companyId)
            ->pluck('id');

        if ($contentIds->isEmpty()) {
            return [
                'views_by_day' => $this->formatViewsByDay(collect(), $from, $to),
                'totals' => ['page_views' => 0, 'unique_visitors' => 0, 'bot_views' => 0],
                'top_articles' => [],
            ];
        }

        $rows = $this->dailyRows($from, $to, null, $contentIds->all());

        return [
            'views_by_day' => $this->formatViewsByDay($rows, $from, $to),
            'totals' => $this->sumTotals($rows),
            'top_articles' => $this->topArticles($from, $to, $contentIds->all()),
        ];
    }

    /**
     * @return array{
     *     views_by_day: list<array{date: string, views: int, uniques: int, bot_views: int}>,
     *     totals: array{page_views: int, unique_visitors: int, bot_views: int},
     *     top_articles: list<array<string, mixed>>,
     * }
     */
    public function getPlatformOverview(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = $this->dailyRows($from, $to);

        return [
            'views_by_day' => $this->formatViewsByDay($rows, $from, $to),
            'totals' => $this->sumTotals($rows),
            'top_articles' => $this->topArticles($from, $to),
        ];
    }

    public function totalViewsLast30Days(): int
    {
        $from = now()->subDays(30)->startOfDay();
        $to = now()->endOfDay();

        return (int) EditorialContentDailyStat::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->sum('page_views');
    }

    public function resolveDateRange(?string $from, ?string $to): array
    {
        $end = $to !== null && $to !== ''
            ? Carbon::parse($to)->endOfDay()
            : now()->endOfDay();

        $start = $from !== null && $from !== ''
            ? Carbon::parse($from)->startOfDay()
            : $end->copy()->subDays(29)->startOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    /**
     * @param  list<int>|null  $contentIds
     * @return Collection<int, object{date: string, page_views: int, unique_visitors: int, bot_views: int}>
     */
    private function dailyRows(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $contentId = null,
        ?array $contentIds = null,
    ): Collection {
        $query = EditorialContentDailyStat::query()
            ->select(
                'date',
                DB::raw('SUM(page_views) as page_views'),
                DB::raw('SUM(unique_visitors) as unique_visitors'),
                DB::raw('SUM(bot_views) as bot_views'),
            )
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->groupBy('date')
            ->orderBy('date');

        if ($contentId !== null) {
            $query->where('content_id', $contentId);
        } elseif ($contentIds !== null) {
            $query->whereIn('content_id', $contentIds);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, object{date: string, page_views: int, unique_visitors: int, bot_views: int}>  $rows
     * @return list<array{date: string, views: int, uniques: int, bot_views: int}>
     */
    private function formatViewsByDay(Collection $rows, CarbonInterface $from, CarbonInterface $to): array
    {
        $indexed = $rows->keyBy(fn ($row): string => Carbon::parse((string) $row->date)->toDateString());

        $series = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->toDateString();
            $row = $indexed->get($key);

            $series[] = [
                'date' => $key,
                'views' => (int) ($row->page_views ?? 0),
                'uniques' => (int) ($row->unique_visitors ?? 0),
                'bot_views' => (int) ($row->bot_views ?? 0),
            ];

            $cursor->addDay();
        }

        return $series;
    }

    /**
     * @param  Collection<int, object{date: string, page_views: int, unique_visitors: int, bot_views: int}>  $rows
     * @return array{page_views: int, unique_visitors: int, bot_views: int}
     */
    private function sumTotals(Collection $rows): array
    {
        return [
            'page_views' => (int) $rows->sum('page_views'),
            'unique_visitors' => (int) $rows->sum('unique_visitors'),
            'bot_views' => (int) $rows->sum('bot_views'),
        ];
    }

    /**
     * @param  list<int>|null  $contentIds
     * @return list<array<string, mixed>>
     */
    private function topArticles(CarbonInterface $from, CarbonInterface $to, ?array $contentIds = null, int $limit = 10): array
    {
        $query = EditorialContentDailyStat::query()
            ->select(
                'content_id',
                DB::raw('SUM(page_views) as page_views'),
                DB::raw('SUM(unique_visitors) as unique_visitors'),
            )
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->groupBy('content_id')
            ->orderByDesc('page_views')
            ->limit($limit);

        if ($contentIds !== null) {
            $query->whereIn('content_id', $contentIds);
        }

        $ranked = $query->get();

        if ($ranked->isEmpty()) {
            return [];
        }

        $contents = EditorialContent::query()
            ->whereIn('id', $ranked->pluck('content_id'))
            ->get()
            ->keyBy('id');

        return $ranked
            ->map(function ($row) use ($contents): ?array {
                $content = $contents->get($row->content_id);
                if ($content === null) {
                    return null;
                }

                return [
                    'uuid' => $content->uuid,
                    'title' => $content->title,
                    'slug' => $content->slug,
                    'rubric_slug' => $content->rubric_slug,
                    'content_type' => $content->content_type->value,
                    'page_views' => (int) $row->page_views,
                    'unique_visitors' => (int) $row->unique_visitors,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
