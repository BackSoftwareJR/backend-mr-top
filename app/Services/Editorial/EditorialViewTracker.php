<?php

declare(strict_types=1);

namespace App\Services\Editorial;

use App\Enums\EditorialContentStatus;
use App\Models\EditorialContent;
use App\Models\EditorialContentDailyStat;
use App\Models\EditorialViewEvent;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EditorialViewTracker
{
    /**
     * @var list<string>
     */
    private const BOT_PATTERNS = [
        'bot',
        'crawl',
        'spider',
        'slurp',
        'mediapartners',
        'googlebot',
        'bingbot',
        'baiduspider',
        'yandexbot',
        'duckduckbot',
        'facebookexternalhit',
        'twitterbot',
        'linkedinbot',
        'curl/',
        'wget/',
        'python-requests',
        'go-http-client',
        'headlesschrome',
        'puppeteer',
        'phantomjs',
        'semrush',
        'ahrefs',
    ];

    public function track(EditorialContent $content, Request $request, ?CarbonInterface $at = null): void
    {
        if (! $this->isTrackable($content)) {
            return;
        }

        $date = ($at ?? now())->toDateString();
        $userAgent = $request->userAgent() ?? '';

        if ($this->isBot($userAgent)) {
            $this->incrementBotView($content->id, $date);

            return;
        }

        $this->incrementHumanView($content->id, $date, $request->ip() ?? '', $userAgent);
    }

    public function isTrackable(EditorialContent $content): bool
    {
        if ($content->noindex) {
            return false;
        }

        if ($content->status !== EditorialContentStatus::Published) {
            return false;
        }

        if ($content->published_at === null || $content->published_at->isFuture()) {
            return false;
        }

        return true;
    }

    public function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return true;
        }

        $normalized = strtolower($userAgent);

        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function visitorHash(string $ip, string $userAgent): string
    {
        return hash('sha256', $ip.'|'.$userAgent.'|'.config('app.key'));
    }

    private function incrementBotView(int $contentId, string $date): void
    {
        DB::transaction(function () use ($contentId, $date): void {
            $this->dailyStatFor($contentId, $date)->increment('bot_views');
        });
    }

    private function incrementHumanView(int $contentId, string $date, string $ip, string $userAgent): void
    {
        DB::transaction(function () use ($contentId, $date, $ip, $userAgent): void {
            $stat = $this->dailyStatFor($contentId, $date);
            $stat->increment('page_views');

            $hash = $this->visitorHash($ip, $userAgent);
            $inserted = EditorialViewEvent::query()->insertOrIgnore([
                'content_id' => $contentId,
                'date' => Carbon::parse($date)->toDateString(),
                'visitor_hash' => $hash,
                'created_at' => now(),
            ]);

            if ($inserted === 1) {
                $stat->increment('unique_visitors');
            }
        });
    }

    private function dailyStatFor(int $contentId, string $date): EditorialContentDailyStat
    {
        $existing = EditorialContentDailyStat::query()
            ->where('content_id', $contentId)
            ->whereDate('date', $date)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return EditorialContentDailyStat::query()->create([
                'content_id' => $contentId,
                'date' => Carbon::parse($date)->toDateString(),
                'page_views' => 0,
                'unique_visitors' => 0,
                'bot_views' => 0,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            return EditorialContentDailyStat::query()
                ->where('content_id', $contentId)
                ->whereDate('date', $date)
                ->firstOrFail();
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique constraint')
            || str_contains($message, 'duplicate entry')
            || $exception->getCode() === '23000';
    }
}
