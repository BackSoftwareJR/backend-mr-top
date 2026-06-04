<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\PrivacyErasureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDataErasureRequest implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $erasureRequestId,
    ) {}

    public function handle(PrivacyErasureService $privacyErasureService): void
    {
        $privacyErasureService->processErasureRequest($this->erasureRequestId);
    }
}
