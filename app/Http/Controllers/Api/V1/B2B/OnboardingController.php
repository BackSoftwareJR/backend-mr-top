<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2B;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\B2B\SubmitOnboardingRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Services\B2bOnboardingService;
use App\Services\B2bTrustQuestionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly B2bOnboardingService $onboardingService,
        private readonly B2bTrustQuestionsService $trustQuestionsService,
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
            'coverage_zone' => ['sometimes', 'nullable', 'array'],
            'coverage_zone.center_lat' => ['required_with:coverage_zone', 'numeric', 'between:-90,90'],
            'coverage_zone.center_lng' => ['required_with:coverage_zone', 'numeric', 'between:-180,180'],
            'coverage_zone.radius_km' => ['required_with:coverage_zone', 'numeric', 'min:0.5', 'max:80'],
            'coverage_zone.label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'coverage_zone.geocode_place_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'coverage_zone.geocode_meta' => ['sometimes', 'nullable', 'array'],
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

    public function submit(SubmitOnboardingRequest $request): JsonResponse
    {
        $user = $request->user();
        $company = $this->onboardingService->companyForUser($user);
        $validated = $request->validated();

        return ApiEnvelope::success($this->onboardingService->submit(
            $company,
            $request,
            $user,
            [
                'terms_text_hash' => $validated['terms_text_hash'],
                'policy_version' => $validated['policy_version'] ?? null,
            ],
        ));
    }

    public function status(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());

        return ApiEnvelope::success($this->onboardingService->status($company));
    }

    public function trustQuestions(Request $request): JsonResponse
    {
        $company = $this->onboardingService->companyForUser($request->user());
        $sector = (string) ($request->query('sector')
            ?? ($company->dynamic_attributes['sector'] ?? 'rsa'));

        return ApiEnvelope::success([
            'sector' => $this->trustQuestionsService->normalizeSector($sector),
            'questions' => $this->trustQuestionsService->forSector($sector),
        ]);
    }
}
