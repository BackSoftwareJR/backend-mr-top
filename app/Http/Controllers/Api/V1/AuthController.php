<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Auth\LoginRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\CompanyResource;
use App\Http\Resources\V1\UserResource;
use App\Services\B2bAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly B2bAuthService $b2bAuthService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->b2bAuthService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            'auth_token',
        );

        return ApiEnvelope::success([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
            'company' => $result['company'] !== null
                ? new CompanyResource($result['company'])
                : null,
        ], Response::HTTP_OK);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiEnvelope::success(['success' => true]);
    }
}
