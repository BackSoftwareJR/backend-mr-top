<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2C;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\B2C\StoreLeadRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\LeadResource;
use App\Services\LeadSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LeadSubmissionController extends Controller
{
    public function __construct(
        private readonly LeadSubmissionService $leadSubmissionService,
    ) {}

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = $this->leadSubmissionService->submit($request, Auth::user());

        return ApiEnvelope::success(
            [
                'lead' => new LeadResource($lead),
                'job_id' => (string) Str::ulid(),
            ],
            Response::HTTP_CREATED,
        );
    }
}
