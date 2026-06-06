<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EditorialIndexRule;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class EditorialPermissionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
        'editorial.view',
        'editorial.create',
        'editorial.edit',
        'editorial.publish',
        'editorial.moderate',
        'editorial.index.manage',
        'editorial.seo.approve',
        'editorial.agent',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
            );
        }

        $allPermissionIds = Permission::query()
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id');

        $permissionIds = static fn (array $names) => Permission::query()
            ->whereIn('name', $names)
            ->pluck('id');

        $roles = [
            'chief_editor' => $allPermissionIds,
            'editor' => $permissionIds(['editorial.view', 'editorial.create', 'editorial.edit']),
            'reviewer' => $permissionIds(['editorial.view', 'editorial.moderate', 'editorial.seo.approve']),
            'structure_author' => $permissionIds(['editorial.create']),
            'editorial_agent' => $permissionIds(['editorial.agent']),
        ];

        foreach ($roles as $roleName => $ids) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
            );

            $role->permissions()->syncWithoutDetaching($ids);
        }

        EditorialIndexRule::query()->updateOrCreate(
            ['rubric_slug' => null],
            [
                'include_in_sitemap' => true,
                'include_in_internal_search' => true,
                'noindex_default' => false,
                'exclude_from_crawl' => false,
                'is_active' => true,
                'notes' => 'Regole globali di indicizzazione editoriali.',
            ],
        );
    }
}
