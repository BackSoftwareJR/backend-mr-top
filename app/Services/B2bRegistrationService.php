<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Mail\B2bWelcomeMail;
use App\Models\Company;
use App\Models\Sector;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class B2bRegistrationService
{
    public function __construct(
        private readonly B2bAuthService $b2bAuthService,
        private readonly B2bCompanyProfileService $companyProfileService,
        private readonly ConsentLogService $consentLogService,
    ) {}

    /**
     * @param  array{consent_text_hash: string, policy_version?: string|null}  $consentPayload
     * @return array{user: User, company: Company, token: string}
     */
    public function register(
        Request $request,
        string $email,
        string $organizationName,
        string $legalName,
        array $consentPayload,
    ): array {
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Questo indirizzo email è già registrato.'],
            ]);
        }

        $sector = Sector::query()->where('slug', 'senior-care')->firstOrFail();

        $result = DB::transaction(function () use ($request, $email, $organizationName, $legalName, $sector, $consentPayload): array {
            $user = User::query()->create([
                'uuid' => (string) Str::uuid(),
                'email' => Str::lower($email),
                'name' => $organizationName,
                'user_type' => UserType::B2b,
            ]);

            $company = Company::query()->create([
                'uuid' => (string) Str::uuid(),
                'sector_id' => $sector->id,
                'organization_name' => $organizationName,
                'legal_name' => $legalName,
                'vetting_status' => VettingStatus::InProgress,
            ]);

            $company->users()->attach($user->id, ['role' => 'owner']);

            Wallet::query()->create([
                'company_id' => $company->id,
                'balance_credits' => 0,
                'total_spent_credits' => 0,
                'currency' => 'EUR',
            ]);

            $this->companyProfileService->ensureProfileForCompany($company);

            $this->consentLogService->recordB2bRegisterConsents(
                $request,
                $user,
                $consentPayload['consent_text_hash'],
                $consentPayload['policy_version'] ?? null,
            );

            $token = $user->createToken('b2b-register')->plainTextToken;

            return [
                'user' => $user,
                'company' => $company,
                'token' => $token,
            ];
        });

        Mail::to($result['user']->email)->queue(
            new B2bWelcomeMail($result['user']->name ?? $organizationName),
        );

        return $result;
    }
}
