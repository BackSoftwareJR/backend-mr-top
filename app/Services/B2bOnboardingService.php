<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\TrustTestStatus;
use App\Enums\VettingStatus;
use App\Http\Resources\V1\CompanyCoverageZoneResource;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\TrustTest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class B2bOnboardingService
{
    public function __construct(
        private readonly ConsentLogService $consentLogService,
        private readonly B2bCoverageZoneService $coverageZoneService,
    ) {}

    /**
     * @return array{status: string, step: int, data: array<string, mixed>}
     */
    public function getState(Company $company): array
    {
        $trustTest = $company->trustTests()->latest()->first();

        $coverageZone = $company->coverageZone;

        return [
            'status' => $company->vetting_status->value,
            'step' => $this->currentStep($company),
            'data' => [
                'vat' => $company->vat_number,
                'sdi' => $company->sdi_code,
                'visura' => $company->documents()->where('type', DocumentType::Visura)->latest()->value('original_name'),
                'identity_doc' => $company->documents()->where('type', DocumentType::Identity)->latest()->value('original_name'),
                'dynamic' => $company->dynamic_attributes ?? [],
                'schedule' => $company->schedule ?? [],
                'trust_answers' => $trustTest?->answers ?? [],
                'coverage_zone' => $coverageZone !== null
                    ? (new CompanyCoverageZoneResource($coverageZone))->resolve()
                    : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array{data: array<string, mixed>, status: string}
     */
    public function patch(Company $company, array $patch): array
    {
        return DB::transaction(function () use ($company, $patch): array {
            if (isset($patch['vat'])) {
                $company->vat_number = $patch['vat'];
            }
            if (isset($patch['sdi'])) {
                $company->sdi_code = $patch['sdi'];
            }
            if (isset($patch['dynamic'])) {
                $company->dynamic_attributes = array_merge(
                    $company->dynamic_attributes ?? [],
                    $patch['dynamic'],
                );
            }
            if (isset($patch['schedule'])) {
                $company->schedule = array_merge($company->schedule ?? [], $patch['schedule']);
            }
            if (isset($patch['trust_answers'])) {
                $this->upsertTrustTest($company, $patch['trust_answers']);
            }
            if (array_key_exists('coverage_zone', $patch)) {
                if ($patch['coverage_zone'] === null) {
                    $this->coverageZoneService->delete($company);
                } else {
                    $this->coverageZoneService->upsert($company, $patch['coverage_zone']);
                }
            }

            if ($company->vetting_status === VettingStatus::Draft) {
                $company->vetting_status = VettingStatus::InProgress;
            }

            $company->save();

            return [
                'data' => $this->getState($company->fresh())['data'],
                'status' => $company->vetting_status->value,
            ];
        });
    }

    /**
     * @return array{type: string, file_name: string, path: string}
     */
    public function uploadDocument(Company $company, string $type, UploadedFile $file): array
    {
        $docType = match ($type) {
            'visura' => DocumentType::Visura,
            'identity' => DocumentType::Identity,
            default => DocumentType::Visura,
        };

        $path = $file->store("companies/{$company->uuid}/documents", 'local');

        CompanyDocument::query()->create([
            'company_id' => $company->id,
            'type' => $docType,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return [
            'type' => $type,
            'file_name' => $file->getClientOriginalName(),
            'path' => Storage::disk('local')->path($path),
        ];
    }

    /**
     * @param  array{terms_text_hash: string, policy_version?: string|null}  $consentPayload
     */
    public function submit(Company $company, Request $request, User $user, array $consentPayload): array
    {
        if (! $company->coverageZone()->exists()) {
            throw ValidationException::withMessages([
                'coverage_zone' => ['Devi configurare la zona di copertura prima di inviare il profilo.'],
            ]);
        }

        return DB::transaction(function () use ($company, $request, $user, $consentPayload): array {
            $this->consentLogService->recordB2bOnboardingSubmitConsents(
                $request,
                $user,
                $consentPayload['terms_text_hash'],
                $consentPayload['policy_version'] ?? null,
            );

            $company->update(['vetting_status' => VettingStatus::PendingReview]);

            $trust = $company->trustTests()->latest()->first();
            if ($trust !== null) {
                $trust->update([
                    'status' => TrustTestStatus::Submitted,
                    'submitted_at' => now(),
                ]);
            }

            return ['status' => VettingStatus::PendingReview->value];
        });
    }

    /**
     * @return array{status: string, onboarding_complete: bool, redirect_to: string, rejection_reason?: string|null}
     */
    public function status(Company $company): array
    {
        $vettingStatus = $company->vetting_status;

        $payload = [
            'status' => $vettingStatus->value,
            'onboarding_complete' => $vettingStatus === VettingStatus::Approved,
            'redirect_to' => $this->redirectForVettingStatus($vettingStatus),
        ];

        if ($vettingStatus === VettingStatus::Rejected) {
            $payload['rejection_reason'] = $company->rejection_reason;
        }

        return $payload;
    }

    public function redirectForVettingStatus(VettingStatus $vettingStatus): string
    {
        return match ($vettingStatus) {
            VettingStatus::Approved => '/pro/dashboard',
            default => '/pro/onboarding',
        };
    }

    public function companyForUser(User $user): Company
    {
        return $user->companies()->firstOrFail();
    }

    private function currentStep(Company $company): int
    {
        if ($company->coverageZone()->exists()) {
            return 3;
        }

        if ($company->vat_number && $company->sdi_code) {
            return 2;
        }

        return 1;
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function upsertTrustTest(Company $company, array $answers): void
    {
        $test = $company->trustTests()->latest()->first();

        if ($test === null) {
            TrustTest::query()->create([
                'company_id' => $company->id,
                'sector_id' => $company->sector_id,
                'answers' => $answers,
                'status' => TrustTestStatus::Draft,
            ]);

            return;
        }

        $test->update([
            'answers' => array_merge($test->answers ?? [], $answers),
            'status' => TrustTestStatus::Draft,
        ]);
    }
}
