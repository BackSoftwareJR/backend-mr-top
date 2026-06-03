<?php

namespace App\Http\Resources\V1;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * B2B partner company profile (non-admin view).
 *
 * API contract:
 * - id: public uuid (RFC 4122)
 * - organization_name: display name
 * - legal_name: registered legal entity name
 * - vetting_status: draft | in_progress | pending_review | approved | rejected | suspended
 * - tier: company tier enum value
 * - city: optional location
 * - approved_at: ISO-8601 when approved
 *
 * Hidden: internal id, vat_number, sdi_code, dynamic_attributes, schedule, rejection_reason
 */
class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Company $company */
        $company = $this->resource;

        return [
            'id' => $company->uuid,
            'organization_name' => $company->organization_name,
            'legal_name' => $company->legal_name,
            'vetting_status' => $company->vetting_status->value,
            'tier' => $company->tier?->value,
            'city' => $company->city,
            'approved_at' => $company->approved_at?->toIso8601String(),
        ];
    }
}
