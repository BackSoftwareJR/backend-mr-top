<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class B2bAuthService
{
    public function __construct(
        private readonly B2bOnboardingService $b2bOnboardingService,
    ) {}

    /**
     * @return array{token: string, user: User, company: Company|null, redirect_to: string}
     */
    public function login(string $email, string $password, string $deviceName = 'b2b-api'): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenziali non valide.'],
            ]);
        }

        if ($user->user_type !== UserType::B2b) {
            throw ValidationException::withMessages([
                'email' => ['Questo account non è abilitato al portale partner.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($deviceName)->plainTextToken;
        $company = $user->companies()->first();

        return [
            'token' => $token,
            'user' => $user,
            'company' => $company,
            'redirect_to' => $company !== null
                ? $this->b2bOnboardingService->redirectForVettingStatus($company->vetting_status)
                : '/pro/onboarding',
        ];
    }
}
