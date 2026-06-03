<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\CrmClientResource;
use App\Services\B2bAppointmentService;
use App\Services\B2bOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentsController extends Controller
{
    public function __construct(
        private readonly B2bAppointmentService $appointmentService,
        private readonly B2bOnboardingService $onboardingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());
        $appointments = $this->appointmentService->list(
            $company,
            $request->query('from'),
            $request->query('to'),
        );

        return ApiEnvelope::success([
            'appointments' => $appointments->map(fn ($a) => [
                'id' => $a->id,
                'client_id' => 'CRM-'.$a->lead_match_id,
                'cliente' => $a->client_name,
                'date' => $a->scheduled_date?->toDateString(),
                'time' => $a->scheduled_time,
                'note' => $a->note,
            ])->values()->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'string'],
            'date' => ['required', 'date'],
            'time' => ['required', 'string', 'max:16'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $company = $this->onboardingService->companyForUser($request->user());
        $result = $this->appointmentService->schedule(
            $company,
            $validated['client_id'],
            $validated['date'],
            $validated['time'],
            $validated['note'] ?? null,
        );

        return ApiEnvelope::success([
            'appointment' => [
                'id' => $result['appointment']->id,
                'date' => $result['appointment']->scheduled_date?->toDateString(),
                'time' => $result['appointment']->scheduled_time,
            ],
            'client' => new CrmClientResource($result['client']),
        ]);
    }
}
