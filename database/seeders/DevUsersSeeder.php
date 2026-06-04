<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CompanyTier;
use App\Enums\CompanyUserRole;
use App\Enums\LeadStatus;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\CompanyProfile;
use App\Models\Lead;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent dev users for local God Mode, consumer searches, and partner flows.
 *
 * @see backend/docs/DEV_SEED.md
 */
class DevUsersSeeder extends Seeder
{
    private const string CONSUMER_EMAIL = 'consumer@wenando.test';

    private const string APPROVED_PARTNER_EMAIL = 'partner@care.it';

    private const string PENDING_PARTNER_EMAIL = 'partner-pending@wenando.test';

    private const string CONSUMER_LEAD_PUBLIC_REF = 'LD-SEED-CONSUMER';

    private const int APPROVED_PARTNER_WALLET_CREDITS = 150;

    public function run(): void
    {
        $sector = Sector::query()->where('slug', 'senior-care')->first();

        if ($sector === null) {
            return;
        }

        $password = Hash::make('password');

        $this->seedSuperadmin($password);
        $this->seedConsumer($sector, $password);
        $this->seedApprovedPartner($sector, $password);
        $this->seedPendingPartner($sector, $password);
    }

    private function seedSuperadmin(string $password): void
    {
        $email = env('SEED_SUPERADMIN_EMAIL', 'admin@wenando.test');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Wenando Super Admin',
                'password' => $password,
                'user_type' => UserType::Superadmin,
                'email_verified_at' => now(),
            ],
        );

        $this->assignRole($user, 'super_admin');
    }

    private function seedConsumer(Sector $sector, string $password): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => self::CONSUMER_EMAIL],
            [
                'name' => 'Demo Consumer',
                'password' => $password,
                'user_type' => UserType::Consumer,
                'email_verified_at' => now(),
            ],
        );

        $this->assignRole($user, 'consumer');

        Lead::query()->updateOrCreate(
            ['public_ref' => self::CONSUMER_LEAD_PUBLIC_REF],
            [
                'sector_id' => $sector->id,
                'user_id' => $user->id,
                'status' => LeadStatus::Routed,
                'payload' => [
                    'autonomy' => 'parziale',
                    'location' => ['label' => 'Milano (MI)', 'value' => 'milano-mi'],
                    'budget' => ['min' => 1500, 'max' => 2500],
                    'contact' => [
                        'nome' => 'Giulia',
                        'telefono' => '+39 333 000 0001',
                    ],
                ],
                'contact_name' => 'Giulia',
                'contact_phone' => '+39 333 000 0001',
                'contact_email' => self::CONSUMER_EMAIL,
                'location_label' => 'Milano (MI)',
                'budget_min' => 1500,
                'budget_max' => 2500,
                'need_summary' => 'Assistenza domiciliare per genitore con autonomia parziale',
                'title' => 'Senior Care · Assistenza per autonomia parziale',
            ],
        );
    }

    private function seedApprovedPartner(Sector $sector, string $password): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => self::APPROVED_PARTNER_EMAIL],
            [
                'name' => 'Care Partner Owner',
                'password' => $password,
                'user_type' => UserType::B2b,
                'email_verified_at' => now(),
            ],
        );

        $company = Company::query()->updateOrCreate(
            [
                'organization_name' => 'Care Partner Italia',
                'sector_id' => $sector->id,
            ],
            [
                'legal_name' => 'Care Partner Italia S.r.l.',
                'city' => 'Milano',
                'vetting_status' => VettingStatus::Approved,
                'tier' => CompanyTier::Growth,
                'approved_at' => now(),
                'rejected_at' => null,
                'rejection_reason' => null,
                'dynamic_attributes' => [
                    'sector' => 'adi',
                    'capacity' => 25,
                    'nonSelfSufficient' => true,
                    'nightStaff' => true,
                ],
            ],
        );

        $company->users()->syncWithoutDetaching([
            $user->id => ['role' => CompanyUserRole::Owner->value],
        ]);

        $this->assignRole($user, 'partner_owner', $company->id);

        CompanyProfile::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'display_name' => 'Care Partner Italia',
                'service_type' => 'Assistenza Domiciliare',
                'tagline' => 'Partner demo approvato per marketplace e wallet',
                'description' => 'Struttura di test con profilo completo e crediti wallet per unlock lead.',
                'pros' => [
                    'Profilo verificato in dev seed',
                    'Wallet con crediti di test',
                    'Ideale per flussi B2B locali',
                ],
                'image_url' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&h=300&fit=crop&q=80',
                'location_label' => 'Milano, Zona Porta Nuova',
                'contact_hint' => 'Account demo: usa OTP o password locale documentata.',
            ],
        );

        Wallet::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'balance_credits' => self::APPROVED_PARTNER_WALLET_CREDITS,
                'total_spent_credits' => 0,
                'currency' => 'EUR',
            ],
        );
    }

    private function seedPendingPartner(Sector $sector, string $password): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => self::PENDING_PARTNER_EMAIL],
            [
                'name' => 'Pending Partner Owner',
                'password' => $password,
                'user_type' => UserType::B2b,
                'email_verified_at' => now(),
            ],
        );

        $company = Company::query()->updateOrCreate(
            [
                'organization_name' => 'Residenza In Attesa',
                'sector_id' => $sector->id,
            ],
            [
                'legal_name' => 'Residenza In Attesa S.r.l.',
                'city' => 'Roma',
                'vetting_status' => VettingStatus::PendingReview,
                'tier' => CompanyTier::Starter,
                'approved_at' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'dynamic_attributes' => [
                    'sector' => 'rsa',
                    'capacity' => 40,
                    'nonSelfSufficient' => true,
                    'nightStaff' => true,
                ],
            ],
        );

        $company->users()->syncWithoutDetaching([
            $user->id => ['role' => CompanyUserRole::Owner->value],
        ]);

        $this->assignRole($user, 'partner_owner', $company->id);
    }

    private function assignRole(User $user, string $roleName, ?int $companyId = null): void
    {
        $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

        if ($role === null) {
            return;
        }

        $pivot = ['company_id' => $companyId];

        $existing = $user->roles()
            ->where('roles.id', $role->id)
            ->wherePivot('company_id', $companyId)
            ->exists();

        if (! $existing) {
            $user->roles()->attach($role->id, $pivot);
        }
    }
}
