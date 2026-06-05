<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Services\B2bOnboardingService;
use App\Services\GoogleCalendar\GoogleCalendarOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleCalendarController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarOAuthService $oauthService,
        private readonly B2bOnboardingService $onboardingService,
    ) {}

    public function connect(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());
        $result = $this->oauthService->buildAuthorizationUrl($company, $request->user());

        return ApiEnvelope::success([
            'authorization_url' => $result['url'],
            'state' => $result['state'],
        ]);
    }

    public function callback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $result = $this->oauthService->handleCallback(
            $validated['code'],
            $validated['state'],
        );

        return ApiEnvelope::success($result);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());
        $this->oauthService->disconnect($company);

        return ApiEnvelope::success(['disconnected' => true]);
    }

    public function status(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());

        return ApiEnvelope::success($this->oauthService->getStatus($company));
    }
}
