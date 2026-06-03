<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2C;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\Lead;
use App\Services\B2cLeadResultsService;
use Illuminate\Http\JsonResponse;

class LeadResultsController extends Controller
{
    public function __construct(
        private readonly B2cLeadResultsService $resultsService,
    ) {}

    public function show(string $uuid): JsonResponse
    {
        $lead = Lead::query()->where('uuid', $uuid)->firstOrFail();

        $status = $this->resultsService->status($lead);

        return ApiEnvelope::success([
            'lead' => [
                'uuid' => $lead->uuid,
                'status' => $lead->status->value,
                'public_ref' => $lead->public_ref,
            ],
            'status' => $status['status'],
            'match_count' => $status['match_count'] ?? null,
        ]);
    }

    public function status(string $uuid): JsonResponse
    {
        $lead = Lead::query()->where('uuid', $uuid)->firstOrFail();

        return ApiEnvelope::success($this->resultsService->status($lead));
    }

    public function results(string $uuid): JsonResponse
    {
        $lead = Lead::query()->where('uuid', $uuid)->firstOrFail();

        return ApiEnvelope::success($this->resultsService->results($lead));
    }

    public function matches(string $uuid): JsonResponse
    {
        $lead = Lead::query()->where('uuid', $uuid)->firstOrFail();
        $matches = $this->resultsService->matches($lead);

        return ApiEnvelope::success([
            'matches' => $matches->map(fn ($m) => [
                'id' => (string) $m->id,
                'match_score' => $m->match_score,
                'rank' => $m->rank,
                'company' => [
                    'id' => $m->company?->id,
                    'name' => $m->company?->organization_name,
                    'city' => $m->company?->city,
                ],
            ])->values()->all(),
        ]);
    }
}
