<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\UserType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSuperadminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_roles_user_and_super_admin_assignment(): void
    {
        $this->assertDatabaseCount('roles', 0);

        $this->artisan('wenando:create-superadmin', ['email' => 'ops@wenando.com'])
            ->assertSuccessful();

        $this->assertDatabaseHas('roles', ['name' => 'super_admin', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['name' => 'admin.access', 'guard_name' => 'web']);

        $user = User::query()->where('email', 'ops@wenando.com')->firstOrFail();

        $this->assertSame(UserType::Superadmin, $user->user_type);
        $this->assertNotNull($user->email_verified_at);

        $role = Role::query()->where('name', 'super_admin')->firstOrFail();

        $this->assertTrue(
            $user->roles()
                ->where('roles.id', $role->id)
                ->wherePivot('company_id', null)
                ->exists(),
        );
    }

    public function test_is_idempotent_and_upgrades_existing_user(): void
    {
        User::factory()->create([
            'email' => 'ops@wenando.com',
            'user_type' => UserType::Consumer,
        ]);

        $this->artisan('wenando:create-superadmin', ['email' => 'ops@wenando.com'])
            ->assertSuccessful();

        $this->artisan('wenando:create-superadmin', ['email' => 'ops@wenando.com'])
            ->assertSuccessful();

        $this->assertSame(1, User::query()->where('email', 'ops@wenando.com')->count());
        $this->assertDatabaseHas('users', [
            'email' => 'ops@wenando.com',
            'user_type' => UserType::Superadmin->value,
        ]);
    }

    public function test_rejects_invalid_email(): void
    {
        $this->artisan('wenando:create-superadmin', ['email' => 'not-an-email'])
            ->assertFailed();
    }

    public function test_accepts_custom_display_name(): void
    {
        $this->artisan('wenando:create-superadmin', [
            'email' => 'ops@wenando.com',
            '--name' => 'Platform Ops',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'ops@wenando.com',
            'name' => 'Platform Ops',
            'user_type' => UserType::Superadmin->value,
        ]);
    }
}
