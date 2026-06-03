<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesPermissionsSeeder extends Seeder
{
    /**
     * Canonical roles and permissions aligned with database_master.sql.
     *
     * Route middleware aliases:
     * - role:partner → consumer excluded; partner_owner, partner_staff, or user_type b2b
     * - role:superadmin → super_admin role or user_type superadmin
     */
    public function run(): void
    {
        $permissions = [
            'leads.view',
            'leads.unlock',
            'wallet.recharge',
            'crm.manage',
            'partners.approve',
            'admin.access',
        ];

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
            );
        }

        $roles = [
            'consumer' => ['leads.view'],
            'partner_owner' => ['leads.view', 'leads.unlock', 'wallet.recharge', 'crm.manage'],
            'partner_staff' => ['leads.view', 'leads.unlock', 'crm.manage'],
            'super_admin' => [
                'leads.view',
                'leads.unlock',
                'wallet.recharge',
                'crm.manage',
                'partners.approve',
                'admin.access',
            ],
        ];

        foreach ($roles as $roleName => $permissionNames) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
            );

            $permissionIds = Permission::query()
                ->whereIn('name', $permissionNames)
                ->pluck('id');

            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
