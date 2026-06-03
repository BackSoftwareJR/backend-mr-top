<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Http\Resources\V1\CompanyResource;
use App\Http\Resources\V1\UserResource;
use App\Services\OtpAuthService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(
        private readonly OtpAuthService $otpAuthService,
        private readonly WalletService $walletService,
    ) {}

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user !== null) {
            $this->otpAuthService->logout($user);
        }

        return ApiEnvelope::success(['success' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = ['user' => new UserResource($user)];

        if ($user->user_type === UserType::B2b) {
            $company = $user->companies()->first();
            if ($company !== null) {
                $data['company'] = new CompanyResource($company);
                $wallet = $this->walletService->getOrCreateWallet($company);
                $data['wallet_summary'] = [
                    'balance_credits' => $wallet->balance_credits,
                    'currency' => $wallet->currency,
                ];
            }
        }

        return ApiEnvelope::success($data);
    }
}
