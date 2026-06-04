<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Services\AdvisorBookingService;
use Illuminate\Http\JsonResponse;

class AdvisorBookingsController extends Controller
{
    public function __construct(
        private readonly AdvisorBookingService $advisorBookingService,
    ) {}

    public function index(): JsonResponse
    {
        return ApiEnvelope::success($this->advisorBookingService->listForAdmin());
    }
}
