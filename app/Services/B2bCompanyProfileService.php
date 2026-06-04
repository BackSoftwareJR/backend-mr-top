<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyProfile;
use App\Models\User;

class B2bCompanyProfileService
{
    public function __construct(
        private readonly B2bOnboardingService $onboardingService,
    ) {}

    /**
     * @return array{company: Company, profile: CompanyProfile|null}
     */
    public function show(User $user): array
    {
        $company = $this->onboardingService->companyForUser($user);
        $company->load('profile');

        return [
            'company' => $company,
            'profile' => $company->profile,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{company: Company, profile: CompanyProfile}
     */
    public function update(User $user, array $attributes, ?int $requestedCompanyId = null): array
    {
        $company = $this->onboardingService->companyForUser($user);

        if ($requestedCompanyId !== null && $requestedCompanyId !== $company->id) {
            abort(403, 'Non puoi modificare il profilo di un\'altra azienda.');
        }

        $profile = $company->profile ?? $this->ensureProfileForCompany($company);
        $profile->update($attributes);

        return [
            'company' => $company->fresh(),
            'profile' => $profile->fresh(),
        ];
    }

    public function ensureProfileForCompany(Company $company): CompanyProfile
    {
        return CompanyProfile::query()->firstOrCreate(
            ['company_id' => $company->id],
            [
                'display_name' => $company->organization_name,
                'service_type' => $this->defaultServiceType($company),
                'location_label' => $company->city,
            ],
        );
    }

    private function defaultServiceType(Company $company): string
    {
        $sector = $company->dynamic_attributes['sector'] ?? null;

        return match ($sector) {
            'rsa' => 'Residenza Sanitaria Assistenziale',
            'adi' => 'Assistenza Domiciliare',
            'centro' => 'Centro diurno',
            'clinica' => 'Clinica / ambulatorio',
            default => 'Assistenza Senior Care',
        };
    }
}
