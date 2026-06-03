<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VettingStatus;
use App\Models\Company;

class PartnerApprovalService
{
    public function approve(Company $company): Company
    {
        $company->forceFill([
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'rejected_at' => null,
            'rejection_reason' => null,
        ])->save();

        return $company->fresh();
    }

    public function reject(Company $company, ?string $reason = null): Company
    {
        $company->forceFill([
            'vetting_status' => VettingStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        return $company->fresh();
    }
}
