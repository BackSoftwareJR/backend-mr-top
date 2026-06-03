<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\OtpPortal;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Auth\OtpRequestRequest;
use App\Http\Requests\V1\Auth\OtpVerifyRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\UserResource;
use App\Services\OtpAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function __construct(
        private readonly OtpAuthService $otpAuthService,
    ) {}

    public function request(OtpRequestRequest $request): JsonResponse
    {
        $data = $this->otpAuthService->requestOtp(
            $request->string('email')->toString(),
            OtpPortal::from($request->string('portal')->toString()),
        );

        return ApiEnvelope::success($data);
    }

    public function verify(OtpVerifyRequest $request): JsonResponse
    {
        $result = $this->otpAuthService->verifyOtp(
            $request->string('email')->toString(),
            $request->string('code')->toString(),
            $request->string('device_name', 'wenando-api')->toString(),
        );

        return ApiEnvelope::success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'redirect_to' => $result['redirect_to'],
        ]);
    }

    public function resendCooldown(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        return ApiEnvelope::success([
            'cooldown_seconds' => $this->otpAuthService->resendCooldownSeconds(
                $request->string('email')->toString(),
            ),
        ]);
    }
}
