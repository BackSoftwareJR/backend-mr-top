<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\Sector;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class B2bRegistrationService
{
    public function __construct(
        private readonly B2bAuthService $b2bAuthService,
    ) {}

    /**
     * @return array{user: User, company: Company, token: string}
     */
    public function register(string $email, string $organizationName, string $legalName): array
    {
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Questo indirizzo email è già registrato.'],
            ]);
        }

        $sector = Sector::query()->where('slug', 'senior-care')->firstOrFail();

        return DB::transaction(function () use ($email, $organizationName, $legalName, $sector): array {
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

            $token = $user->createToken('b2b-register')->plainTextToken;

            return [
                'user' => $user,
                'company' => $company,
                'token' => $token,
            ];
        });
    }
}
