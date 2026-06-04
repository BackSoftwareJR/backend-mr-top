<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\B2B\StoreRegisterRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\CompanyResource;
use App\Http\Resources\V1\UserResource;
use App\Services\B2bRegistrationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RegisterController extends Controller
{
    public function __construct(
        private readonly B2bRegistrationService $registrationService,
    ) {}

    public function store(StoreRegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->registrationService->register(
            $request,
            $validated['email'],
            $validated['organization_name'],
            $validated['legal_name'],
            [
                'consent_text_hash' => $validated['consent_text_hash'],
                'policy_version' => $validated['policy_version'] ?? null,
            ],
        );

        return ApiEnvelope::success([
            'user' => new UserResource($result['user']),
            'company' => new CompanyResource($result['company']),
            'token' => $result['token'],
        ], Response::HTTP_CREATED);
    }
}
