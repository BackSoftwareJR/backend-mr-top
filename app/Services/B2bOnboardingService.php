<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\TrustTestStatus;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\TrustTest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class B2bOnboardingService
{
    /**
     * @return array{status: string, step: int, data: array<string, mixed>}
     */
    public function getState(Company $company): array
    {
        $trustTest = $company->trustTests()->latest()->first();

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

    public function submit(Company $company): array
    {
        $company->update(['vetting_status' => VettingStatus::PendingReview]);

        $trust = $company->trustTests()->latest()->first();
        if ($trust !== null) {
            $trust->update([
                'status' => TrustTestStatus::Submitted,
                'submitted_at' => now(),
            ]);
        }

        return ['status' => VettingStatus::PendingReview->value];
    }

    /**
     * @return array{status: string, redirect_to: string}
     */
    public function status(Company $company): array
    {
        return [
            'status' => $company->vetting_status->value,
            'redirect_to' => match ($company->vetting_status) {
                VettingStatus::Approved => '/pro/dashboard',
                VettingStatus::PendingReview => '/pro/onboarding',
                default => '/pro/onboarding',
            },
        ];
    }

    public function companyForUser(User $user): Company
    {
        return $user->companies()->firstOrFail();
    }

    private function currentStep(Company $company): int
    {
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
