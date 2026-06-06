<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EditorialContent;
use App\Services\Editorial\SuggestInternalLinksService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SuggestInternalLinksJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $contentId,
    ) {}

    public function handle(SuggestInternalLinksService $service): void
    {
        /** @var EditorialContent|null $content */
        $content = EditorialContent::query()->find($this->contentId);

        if ($content === null) {
            return;
        }

        $service->suggest($content);
    }
}
