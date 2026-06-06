<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentStatus;
use App\Enums\EditorialContentType;
use App\Enums\EditorialModerationStatus;
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

class B2bEditorialContentTest extends TestCase
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

    public function test_partner_creates_draft_with_structure_metadata(): void
    {
        [$partner, $company] = $this->partnerUser('Casa Serena RSA');

        Sanctum::actingAs($partner);

        $response = $this->postJson('/api/v1/b2b/editorial/contents', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.content.status', 'draft')
            ->assertJsonPath('data.content.author_type', 'company')
            ->assertJsonPath('data.content.company_id', $company->id)
            ->assertJsonPath('data.content.is_structure_content', true)
            ->assertJsonPath('data.content.author_badge', 'Casa Serena RSA')
            ->assertJsonStructure(['data' => ['content' => ['structure_disclaimer']]]);

        $uuid = $response->json('data.content.uuid');
        $this->assertNotEmpty($uuid);

        $this->assertDatabaseHas('editorial_contents', [
            'uuid' => $uuid,
            'company_id' => $company->id,
            'author_type' => EditorialAuthorType::Company->value,
            'status' => EditorialContentStatus::Draft->value,
        ]);
    }

    public function test_partner_submits_draft_for_review(): void
    {
        [$partner, $company] = $this->partnerUser();

        Sanctum::actingAs($partner);

        $create = $this->postJson('/api/v1/b2b/editorial/contents', $this->validPayload())
            ->assertCreated();

        $uuid = $create->json('data.content.uuid');
        $updatedAt = $create->json('data.content.updated_at');

        $this->postJson('/api/v1/b2b/editorial/contents/'.$uuid.'/submit', [], [
            'If-Match' => $updatedAt,
        ])
            ->assertOk()
            ->assertJsonPath('data.content.status', 'pending_review')
            ->assertJsonPath('data.content.is_structure_content', true);

        $content = EditorialContent::query()->where('uuid', $uuid)->firstOrFail();
        $this->assertSame(EditorialContentStatus::PendingReview, $content->status);

        $this->assertDatabaseHas('editorial_moderation_queue', [
            'content_id' => $content->id,
            'company_id' => $company->id,
            'status' => EditorialModerationStatus::Pending->value,
        ]);
    }

    public function test_partner_cannot_publish_via_admin_transition(): void
    {
        [$partner, $company] = $this->partnerUser();

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'company_id' => $company->id,
            'author_type' => EditorialAuthorType::Company,
            'status' => EditorialContentStatus::Draft,
        ]);

        Sanctum::actingAs($partner);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::Published->value,
        ], [
            'If-Match' => $content->updated_at?->toIso8601String(),
        ])->assertForbidden();
    }

    public function test_partner_cannot_access_other_company_content(): void
    {
        [$partner] = $this->partnerUser();
        $otherCompany = Company::factory()->create([
            'sector_id' => $this->sector->id,
            'organization_name' => 'Altra Struttura',
        ]);

        $foreignContent = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'company_id' => $otherCompany->id,
            'author_type' => EditorialAuthorType::Company,
            'status' => EditorialContentStatus::Draft,
        ]);

        Sanctum::actingAs($partner);

        $this->getJson('/api/v1/b2b/editorial/contents/'.$foreignContent->uuid)
            ->assertForbidden();

        $this->patchJson('/api/v1/b2b/editorial/contents/'.$foreignContent->uuid, [
            'title' => 'Tentativo di modifica',
        ])->assertForbidden();

        $this->postJson('/api/v1/b2b/editorial/contents/'.$foreignContent->uuid.'/submit')
            ->assertForbidden();
    }

    public function test_partner_list_only_shows_own_company_contents(): void
    {
        [$partner, $company] = $this->partnerUser('Struttura Alpha');
        $otherCompany = Company::factory()->create(['sector_id' => $this->sector->id]);

        EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'company_id' => $company->id,
            'author_type' => EditorialAuthorType::Company,
            'title' => 'Contenuto proprio',
        ]);

        EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'company_id' => $otherCompany->id,
            'author_type' => EditorialAuthorType::Company,
            'title' => 'Contenuto altrui',
        ]);

        Sanctum::actingAs($partner);

        $response = $this->getJson('/api/v1/b2b/editorial/contents')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $titles = collect($response->json('data.contents'))->pluck('title')->all();
        $this->assertSame(['Contenuto proprio'], $titles);
    }

    public function test_unknown_content_uuid_returns_not_found(): void
    {
        [$partner] = $this->partnerUser();

        Sanctum::actingAs($partner);

        $this->getJson('/api/v1/b2b/editorial/contents/'.(string) Str::uuid())
            ->assertNotFound();
    }

    public function test_partner_can_update_rejected_content(): void
    {
        [$partner, $company] = $this->partnerUser();

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'company_id' => $company->id,
            'author_type' => EditorialAuthorType::Company,
            'status' => EditorialContentStatus::Rejected,
        ]);

        Sanctum::actingAs($partner);

        $this->patchJson('/api/v1/b2b/editorial/contents/'.$content->uuid, [
            'title' => 'Titolo rivisto',
        ], [
            'If-Match' => $content->updated_at?->toIso8601String(),
        ])
            ->assertOk()
            ->assertJsonPath('data.content.title', 'Titolo rivisto');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'type' => EditorialContentType::Story->value,
            'title' => 'La nostra giornata tipo',
            'rubric_id' => $this->rubric->id,
            'body_blocks' => $this->sampleBlocks(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sampleBlocks(): array
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
                    'html' => '<p>Testo di esempio.</p>',
                ],
            ],
        ];
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function partnerUser(string $organizationName = 'Struttura Partner'): array
    {
        $company = Company::factory()->create([
            'sector_id' => $this->sector->id,
            'organization_name' => $organizationName,
        ]);
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
