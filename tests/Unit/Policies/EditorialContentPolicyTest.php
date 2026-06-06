<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\EditorialContent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Policies\EditorialContentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialContentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private EditorialContentPolicy $policy;

    private Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new EditorialContentPolicy;

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->seedEditorialPermissions();
    }

    public function test_superadmin_has_full_access(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::Superadmin]);
        $content = $this->makeContent();

        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->view($admin, $content));
        $this->assertTrue($this->policy->create($admin));
        $this->assertTrue($this->policy->update($admin, $content));
        $this->assertTrue($this->policy->delete($admin, $content));
        $this->assertTrue($this->policy->publish($admin, $content));
        $this->assertTrue($this->policy->moderate($admin, $content));
        $this->assertTrue($this->policy->approveSeo($admin, $content));
        $this->assertTrue($this->policy->manageIndex($admin));
    }

    public function test_editor_can_create_and_edit_but_not_publish(): void
    {
        $editor = $this->userWithRole('editor');
        $content = $this->makeContent();

        $this->assertTrue($this->policy->viewAny($editor));
        $this->assertTrue($this->policy->view($editor, $content));
        $this->assertTrue($this->policy->create($editor));
        $this->assertTrue($this->policy->update($editor, $content));
        $this->assertFalse($this->policy->publish($editor, $content));
        $this->assertFalse($this->policy->moderate($editor, $content));
        $this->assertFalse($this->policy->approveSeo($editor, $content));
        $this->assertFalse($this->policy->manageIndex($editor));
    }

    public function test_reviewer_can_moderate_and_approve_seo_but_not_edit(): void
    {
        $reviewer = $this->userWithRole('reviewer');
        $content = $this->makeContent();

        $this->assertTrue($this->policy->viewAny($reviewer));
        $this->assertTrue($this->policy->view($reviewer, $content));
        $this->assertFalse($this->policy->create($reviewer));
        $this->assertFalse($this->policy->update($reviewer, $content));
        $this->assertTrue($this->policy->moderate($reviewer, $content));
        $this->assertTrue($this->policy->approveSeo($reviewer, $content));
        $this->assertFalse($this->policy->publish($reviewer, $content));
    }

    public function test_partner_can_manage_own_company_draft_only(): void
    {
        [$partner, $company] = $this->partnerUser();
        $ownDraft = $this->makeContent([
            'company_id' => $company->id,
            'author_type' => EditorialAuthorType::Company,
            'status' => EditorialContentStatus::Draft,
        ]);
        $ownPublished = $this->makeContent([
            'company_id' => $company->id,
            'author_type' => EditorialAuthorType::Company,
            'status' => EditorialContentStatus::Published,
        ]);

        $this->assertTrue($this->policy->viewAny($partner));
        $this->assertTrue($this->policy->view($partner, $ownDraft));
        $this->assertTrue($this->policy->create($partner));
        $this->assertTrue($this->policy->update($partner, $ownDraft));
        $this->assertFalse($this->policy->update($partner, $ownPublished));
        $this->assertFalse($this->policy->publish($partner, $ownDraft));

        $ownRejected = $this->makeContent([
            'company_id' => $company->id,
            'author_type' => EditorialAuthorType::Company,
            'status' => EditorialContentStatus::Rejected,
        ]);
        $this->assertTrue($this->policy->update($partner, $ownRejected));
    }

    public function test_partner_cannot_access_other_company_content(): void
    {
        [$partner] = $this->partnerUser();
        $otherCompany = Company::factory()->create(['sector_id' => $this->sector->id]);
        $foreignContent = $this->makeContent([
            'company_id' => $otherCompany->id,
            'author_type' => EditorialAuthorType::Company,
            'status' => EditorialContentStatus::Draft,
        ]);

        $this->assertFalse($this->policy->view($partner, $foreignContent));
        $this->assertFalse($this->policy->update($partner, $foreignContent));
    }

    public function test_chief_editor_has_all_editorial_permissions(): void
    {
        $chief = $this->userWithRole('chief_editor');
        $content = $this->makeContent();

        $this->assertTrue($this->policy->view($chief, $content));
        $this->assertTrue($this->policy->create($chief));
        $this->assertTrue($this->policy->update($chief, $content));
        $this->assertTrue($this->policy->publish($chief, $content));
        $this->assertTrue($this->policy->moderate($chief, $content));
        $this->assertTrue($this->policy->approveSeo($chief, $content));
        $this->assertTrue($this->policy->manageIndex($chief));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeContent(array $overrides = []): EditorialContent
    {
        return EditorialContent::factory()->create(array_merge([
            'sector_id' => $this->sector->id,
        ], $overrides));
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role->id, ['company_id' => null]);

        return $user;
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function partnerUser(): array
    {
        $company = Company::factory()->create(['sector_id' => $this->sector->id]);
        $partner = User::factory()->create(['user_type' => UserType::B2b]);
        $partner->companies()->attach($company->id, ['role' => 'owner']);

        $structureAuthorRole = Role::query()->where('name', 'structure_author')->firstOrFail();
        $partner->roles()->attach($structureAuthorRole->id, ['company_id' => $company->id]);

        return [$partner, $company];
    }

    private function seedEditorialPermissions(): void
    {
        $permissions = [
            'editorial.view',
            'editorial.create',
            'editorial.edit',
            'editorial.publish',
            'editorial.moderate',
            'editorial.index.manage',
            'editorial.seo.approve',
        ];

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $allIds = Permission::query()->whereIn('name', $permissions)->pluck('id');
        $byName = static fn (array $names) => Permission::query()->whereIn('name', $names)->pluck('id');

        $roles = [
            'chief_editor' => $allIds,
            'editor' => $byName(['editorial.view', 'editorial.create', 'editorial.edit']),
            'reviewer' => $byName(['editorial.view', 'editorial.moderate', 'editorial.seo.approve']),
            'structure_author' => $byName(['editorial.create']),
        ];

        foreach ($roles as $roleName => $permissionIds) {
            $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
