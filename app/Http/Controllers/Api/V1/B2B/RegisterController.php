<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\CompanyResource;
use App\Http\Resources\V1\UserResource;
use App\Services\B2bRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegisterController extends Controller
{
    public function __construct(
        private readonly B2bRegistrationService $registrationService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'organization_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->registrationService->register(
            $validated['email'],
            $validated['organization_name'],
            $validated['legal_name'],
        );

        return ApiEnvelope::success([
            'user' => new UserResource($result['user']),
            'company' => new CompanyResource($result['company']),
            'token' => $result['token'],
        ], Response::HTTP_CREATED);
    }
}
