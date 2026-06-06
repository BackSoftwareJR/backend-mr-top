<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EditorialContent;
use App\Services\Editorial\EditorialSeoGroqService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateEditorialSeoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $contentId,
    ) {}

    public function handle(EditorialSeoGroqService $seoGroqService): void
    {
        $content = EditorialContent::query()->with('rubric')->find($this->contentId);

        if ($content === null) {
            return;
        }

        $seoGroqService->generateAndStore($content);
    }
}
