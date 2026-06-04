<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

class PartnerApprovalService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly B2bCompanyProfileService $companyProfileService,
    ) {}

    public function approve(Company $company, User $admin, ?Request $request = null): Company
    {
        $company->forceFill([
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
        ])->save();

        $this->auditLogService->record(
            AuditAction::PartnerApproved,
            $admin,
            $company,
            [
                'company_uuid' => $company->uuid,
                'admin_uuid' => $admin->uuid,
            ],
            $request,
        );

        $this->companyProfileService->ensureProfileForCompany($company);

        return $company->fresh();
    }

    public function reject(Company $company, User $admin, ?string $reason = null, ?Request $request = null): Company
    {
        $company->forceFill([
            'vetting_status' => VettingStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        $this->auditLogService->record(
            AuditAction::PartnerRejected,
            $admin,
            $company,
            [
                'company_uuid' => $company->uuid,
                'admin_uuid' => $admin->uuid,
                'reason' => $reason,
            ],
            $request,
        );

        return $company->fresh();
    }
}
