<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhooks\StoreAgentEditorialDraftRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Services\Editorial\AgentEditorialIngestService;
use Illuminate\Http\JsonResponse;

class AgentEditorialWebhookController extends Controller
{
    public function __construct(
        private readonly AgentEditorialIngestService $ingestService,
    ) {}

    public function store(StoreAgentEditorialDraftRequest $request): JsonResponse
    {
        $result = $this->ingestService->ingest($request->validated());

        return ApiEnvelope::success(
            ['content' => $result],
            $result['idempotent'] ? 200 : 201,
        );
    }
}
