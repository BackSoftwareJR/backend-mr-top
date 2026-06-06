<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentStatus;
use App\Enums\EditorialContentType;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\EditorialContent;
use App\Models\EditorialRubric;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\EditorialRubricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorialContentCrudTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    private EditorialRubric $rubric;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->seed(EditorialRubricSeeder::class);
        $this->seedEditorialPermissions();

        $this->rubric = EditorialRubric::query()->where('slug', 'guide')->firstOrFail();
    }

    public function test_superadmin_can_crud_editorial_content(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::Superadmin]);
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/v1/admin/editorial/contents', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.content.status', 'draft')
            ->assertJsonPath('data.content.content_type', 'article');

        $uuid = $create->json('data.content.uuid');
        $this->assertNotEmpty($uuid);

        $this->getJson('/api/v1/admin/editorial/contents/'.$uuid)
            ->assertOk()
            ->assertJsonPath('data.content.uuid', $uuid)
            ->assertJsonStructure(['data' => ['content' => ['body_blocks', 'rubric']]]);

        $this->patchJson('/api/v1/admin/editorial/contents/'.$uuid, [
            'title' => 'Titolo aggiornato',
            'body_blocks' => $this->sampleBlocks('Nuovo paragrafo'),
        ])
            ->assertOk()
            ->assertJsonPath('data.content.title', 'Titolo aggiornato');

        $this->deleteJson('/api/v1/admin/editorial/contents/'.$uuid)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertSoftDeleted('editorial_contents', ['uuid' => $uuid]);
    }

    public function test_chief_editor_can_crud_editorial_content(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $uuid = $this->postJson('/api/v1/admin/editorial/contents', $this->validPayload())
            ->assertCreated()
            ->json('data.content.uuid');

        $this->patchJson('/api/v1/admin/editorial/contents/'.$uuid, [
            'title' => 'Chief edit',
        ])->assertOk();

        $this->deleteJson('/api/v1/admin/editorial/contents/'.$uuid)->assertOk();
    }

    public function test_editor_can_create_and_update_but_not_delete(): void
    {
        $editor = $this->userWithRole('editor');
        Sanctum::actingAs($editor);

        $uuid = $this->postJson('/api/v1/admin/editorial/contents', $this->validPayload())
            ->assertCreated()
            ->json('data.content.uuid');

        $this->patchJson('/api/v1/admin/editorial/contents/'.$uuid, [
            'excerpt' => 'Excerpt editor',
        ])->assertOk();

        $this->deleteJson('/api/v1/admin/editorial/contents/'.$uuid)
            ->assertForbidden();
    }

    public function test_partner_and_structure_author_cannot_access_admin_routes(): void
    {
        [$partner] = $this->partnerUser();

        Sanctum::actingAs($partner);

        $this->getJson('/api/v1/admin/editorial/contents')->assertForbidden();
        $this->postJson('/api/v1/admin/editorial/contents', $this->validPayload())->assertForbidden();
    }

    public function test_validation_failures_on_create(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::Superadmin]);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/editorial/contents', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.content_type.0', fn ($message) => is_string($message) && $message !== '')
            ->assertJsonPath('error.details.title.0', fn ($message) => is_string($message) && $message !== '')
            ->assertJsonPath('error.details.rubric_id.0', fn ($message) => is_string($message) && $message !== '')
            ->assertJsonPath('error.details.body_blocks.0', fn ($message) => is_string($message) && $message !== '');

        $this->postJson('/api/v1/admin/editorial/contents', array_merge($this->validPayload(), [
            'author_type' => EditorialAuthorType::Company->value,
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('error.details.company_id.0', fn ($message) => is_string($message) && $message !== '');
    }

    public function test_revision_created_on_update(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::Superadmin]);
        Sanctum::actingAs($admin);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'title' => 'Prima versione',
        ]);

        $this->patchJson('/api/v1/admin/editorial/contents/'.$content->uuid, [
            'title' => 'Seconda versione',
        ])->assertOk();

        $this->assertDatabaseHas('editorial_content_revisions', [
            'editorial_content_id' => $content->id,
            'revision_number' => 1,
        ]);

        $revision = $content->revisions()->first();
        $this->assertSame('Prima versione', $revision->snapshot['title']);
        $this->assertSame('Auto-snapshot before update', $revision->change_summary);
    }

    public function test_manual_revision_snapshot_endpoint(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::Superadmin]);
        Sanctum::actingAs($admin);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
        ]);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/revisions', [
            'change_summary' => 'Checkpoint manuale',
        ])
            ->assertCreated()
            ->assertJsonPath('data.revision.revision_number', 1)
            ->assertJsonPath('data.revision.change_summary', 'Checkpoint manuale');

        $this->getJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/revisions')
            ->assertOk()
            ->assertJsonCount(1, 'data.revisions');
    }

    public function test_index_filters_by_status_type_rubric_and_search(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::Superadmin]);
        Sanctum::actingAs($admin);

        EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'title' => 'Guida badante convivente',
            'content_type' => EditorialContentType::Article,
            'status' => EditorialContentStatus::Draft,
        ]);

        EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'title' => 'Storia di cura',
            'content_type' => EditorialContentType::Story,
            'status' => EditorialContentStatus::Published,
        ]);

        $this->getJson('/api/v1/admin/editorial/contents?status=draft')
            ->assertOk()
            ->assertJsonCount(1, 'data.contents');

        $this->getJson('/api/v1/admin/editorial/contents?type=story')
            ->assertOk()
            ->assertJsonCount(1, 'data.contents')
            ->assertJsonPath('data.contents.0.content_type', 'story');

        $this->getJson('/api/v1/admin/editorial/contents?rubric_id='.$this->rubric->id)
            ->assertOk()
            ->assertJsonCount(2, 'data.contents');

        $this->getJson('/api/v1/admin/editorial/contents?q=badante')
            ->assertOk()
            ->assertJsonCount(1, 'data.contents')
            ->assertJsonPath('data.contents.0.title', 'Guida badante convivente');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/admin/editorial/contents')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'content_type' => EditorialContentType::Article->value,
            'title' => 'Come scegliere una badante',
            'rubric_id' => $this->rubric->id,
            'sector_id' => $this->sector->id,
            'body_blocks' => $this->sampleBlocks(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sampleBlocks(string $paragraph = 'Testo di esempio.'): array
    {
        return [
            [
                'id' => (string) Str::uuid(),
                'type' => 'heading',
                'data' => [
                    'level' => 2,
                    'text' => 'Introduzione',
                    'anchor' => 'introduzione',
                ],
            ],
            [
                'id' => (string) Str::uuid(),
                'type' => 'paragraph',
                'data' => [
                    'html' => '<p>'.$paragraph.'</p>',
                ],
            ],
        ];
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
