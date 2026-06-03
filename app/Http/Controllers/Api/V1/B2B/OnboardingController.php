<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Services\B2bOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly B2bOnboardingService $onboardingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());

        return ApiEnvelope::success($this->onboardingService->getState($company));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => ['sometimes', 'array'],
            'vat' => ['sometimes', 'string'],
            'sdi' => ['sometimes', 'string'],
            'dynamic' => ['sometimes', 'array'],
            'schedule' => ['sometimes', 'array'],
            'trust_answers' => ['sometimes', 'array'],
        ]);

        $patch = $validated['data'] ?? $validated;
        unset($patch['data']);
        $company = $this->onboardingService->companyForUser($request->user());

        return ApiEnvelope::success($this->onboardingService->patch($company, $patch));
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:visura,identity'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $company = $this->onboardingService->companyForUser($request->user());

        return ApiEnvelope::success(
            $this->onboardingService->uploadDocument(
                $company,
                $validated['type'],
                $request->file('file'),
            ),
        );
    }

    public function submit(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());

        return ApiEnvelope::success($this->onboardingService->submit($company));
    }

    public function status(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());

        return ApiEnvelope::success($this->onboardingService->status($company));
    }
}
