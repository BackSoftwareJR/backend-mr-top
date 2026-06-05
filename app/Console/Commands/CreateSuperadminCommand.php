<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Idempotent production bootstrap — roles + one superadmin user (OTP login, no demo data).
 */
class CreateSuperadminCommand extends Command
{
    protected $signature = 'wenando:create-superadmin
                            {email? : Super admin email (falls back to SEED_SUPERADMIN_EMAIL)}
                            {--name= : Display name (default: Wenando Super Admin)}';

    /** @var list<string> */
    protected $aliases = ['wenando:fix-superadmin-role'];

    protected $description = 'Seed RBAC roles if missing and create or update one superadmin user (idempotent)';

    public function handle(): int
    {
        $email = $this->resolveEmail();

        if ($email === null) {
            $this->components->error(
                'Provide an email argument or set SEED_SUPERADMIN_EMAIL in .env.',
            );

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email:rfc']],
        );

        if ($validator->fails()) {
            $this->components->error('Invalid email address.');

            foreach ($validator->errors()->all() as $message) {
                $this->line("  {$message}");
            }

            return self::FAILURE;
        }

        $this->call(RolesPermissionsSeeder::class);

        $name = (string) ($this->option('name') ?: 'Wenando Super Admin');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'user_type' => UserType::Superadmin,
                'email_verified_at' => now(),
            ],
        );

        $role = Role::query()
            ->where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->first();

        if ($role === null) {
            $this->components->error('super_admin role missing after RolesPermissionsSeeder.');

            return self::FAILURE;
        }

        $hasRole = $user->roles()
            ->where('roles.id', $role->id)
            ->wherePivot('company_id', null)
            ->exists();

        $roleAttached = false;
        if (! $hasRole) {
            $user->roles()->attach($role->id, ['company_id' => null]);
            $roleAttached = true;
        }

        $created = $user->wasRecentlyCreated;

        $this->components->info(sprintf(
            '%s superadmin %s (%s). Login via /admin OTP (portal=admin).',
            $created ? 'Created' : 'Updated',
            $email,
            $name,
        ));

        if ($roleAttached) {
            $this->components->info('Attached super_admin role (model_has_roles).');
        } elseif ($hasRole) {
            $this->components->info('super_admin role already present.');
        }

        return self::SUCCESS;
    }

    private function resolveEmail(): ?string
    {
        $argument = $this->argument('email');

        if (is_string($argument) && trim($argument) !== '') {
            return Str::lower(trim($argument));
        }

        $fromEnv = env('SEED_SUPERADMIN_EMAIL');

        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return Str::lower(trim($fromEnv));
        }

        return null;
    }
}
