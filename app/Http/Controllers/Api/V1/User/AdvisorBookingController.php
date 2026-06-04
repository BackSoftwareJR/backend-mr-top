<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\User\StoreAdvisorBookingRequest;
use App\Http\Requests\V1\User\UpdateAdvisorBookingRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\Appointment;
use App\Services\AdvisorBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvisorBookingController extends Controller
{
    public function __construct(
        private readonly AdvisorBookingService $advisorBookingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiEnvelope::success(
            $this->advisorBookingService->listForUser($request->user()),
        );
    }

    public function store(StoreAdvisorBookingRequest $request): JsonResponse
    {
        $appointment = $this->advisorBookingService->create(
            $request->user(),
            $request->validated(),
        );

        return ApiEnvelope::success([
            'booking_id' => $appointment->id,
        ]);
    }

    public function update(UpdateAdvisorBookingRequest $request, Appointment $advisorBooking): JsonResponse
    {
        $appointment = $this->advisorBookingService->reschedule(
            $advisorBooking,
            $request->validated(),
        );

        return ApiEnvelope::success([
            'booking' => [
                'id' => $appointment->id,
                'scheduled_date' => $appointment->scheduled_date?->toDateString(),
                'scheduled_time' => substr((string) $appointment->scheduled_time, 0, 5),
                'name' => $appointment->client_name,
                'lead_title' => $appointment->lead?->title,
            ],
        ]);
    }

    public function destroy(Appointment $advisorBooking): JsonResponse
    {
        $this->advisorBookingService->cancel($advisorBooking);

        return ApiEnvelope::success(['cancelled' => true]);
    }
}
