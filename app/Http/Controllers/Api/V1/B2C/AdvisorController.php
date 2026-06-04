<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2C;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Services\AdvisorProfileService;
use Illuminate\Http\JsonResponse;

class AdvisorController extends Controller
{
    public function __construct(
        private readonly AdvisorProfileService $advisorProfiles,
    ) {}

    public function show(): JsonResponse
    {
        return ApiEnvelope::success($this->advisorProfiles->defaultPayload());
    }
}
