<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Editorial\EditorialNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendEditorialReviewDigestJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onConnection('database');
        $this->onQueue('default');
    }

    public function handle(EditorialNotificationService $notificationService): void
    {
        $notificationService->sendReviewDigest();
    }
}
