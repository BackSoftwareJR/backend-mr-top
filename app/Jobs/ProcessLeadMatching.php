<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Lead;
use App\Services\LeadMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessLeadMatching implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $leadId,
    ) {}

    public function handle(LeadMatchingService $matchingService): void
    {
        $lead = Lead::query()->find($this->leadId);

        if ($lead === null) {
            return;
        }

        $matchingService->matchLead($lead);
    }
}
