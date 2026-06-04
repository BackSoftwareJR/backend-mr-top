<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeadStatus;
use App\Http\Requests\V1\B2C\StoreLeadRequest;
use App\Jobs\ProcessLeadMatching;
use App\Models\Lead;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadSubmissionService
{
    public function __construct(
        private readonly ConsentLogService $consentLogService,
    ) {}

    public function submit(StoreLeadRequest $request, ?User $user = null): Lead
    {
        return DB::transaction(function () use ($request, $user): Lead {
            $validated = $request->validated();
            $payload = $validated['payload'];
            $sector = Sector::query()->where('slug', $validated['sector_slug'])->firstOrFail();
            $needSummary = $this->buildNeedSummary($payload['autonomy']);
            $contactEmail = $this->normalizeContactEmail($payload['contact']['email'] ?? null);

            $lead = Lead::query()->create([
                'sector_id' => $sector->id,
                'user_id' => $user?->id,
                'status' => LeadStatus::Processing,
                'payload' => $payload,
                'contact_name' => $payload['contact']['nome'],
                'contact_phone' => $payload['contact']['telefono'],
                'contact_email' => $contactEmail,
                'location_label' => $payload['location']['label'],
                'budget_min' => $payload['budget']['min'],
                'budget_max' => $payload['budget']['max'],
                'need_summary' => $needSummary,
                'title' => $this->buildSearchTitle($sector->name, $needSummary),
            ]);

            $lead->update([
                'public_ref' => sprintf('LD-%d', $lead->id),
            ]);

            $this->consentLogService->recordLeadSubmissionConsents(
                $lead,
                $request,
                $user,
                $validated,
            );

            ProcessLeadMatching::dispatch($lead->id);

            return $lead->fresh();
        });
    }

    private function normalizeContactEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $trimmed = trim($email);

        return $trimmed === '' ? null : Str::lower($trimmed);
    }

    private function buildNeedSummary(string $autonomy): string
    {
        return match ($autonomy) {
            'autosufficiente' => 'Assistenza per persona autosufficiente',
            'parziale' => 'Assistenza per autonomia parziale',
            'non-autosufficiente' => 'Assistenza per persona non autosufficiente',
            default => 'Richiesta assistenza senior care',
        };
    }

    private function buildSearchTitle(string $sectorName, string $needSummary): string
    {
        $title = sprintf('%s · %s', $sectorName, $needSummary);

        return mb_strlen($title) > 255 ? mb_substr($title, 0, 252).'…' : $title;
    }
}
