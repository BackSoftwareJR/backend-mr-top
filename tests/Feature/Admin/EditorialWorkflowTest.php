<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorialWorkflowTest extends TestCase
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

    public function test_draft_to_pending_review_to_published_happy_path_records_workflow_events(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'status' => EditorialContentStatus::Draft,
        ]);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::PendingReview->value,
            'note' => 'Pronto per revisione',
        ], [
            'If-Match' => $content->updated_at?->toIso8601String(),
        ])
            ->assertOk()
            ->assertJsonPath('data.content.status', 'pending_review');

        $content->refresh();
        $this->assertSame(EditorialContentStatus::PendingReview, $content->status);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::Published->value,
            'note' => 'Approvato e pubblicato',
        ], [
            'If-Match' => $content->updated_at?->toIso8601String(),
        ])
            ->assertOk()
            ->assertJsonPath('data.content.status', 'published')
            ->assertJsonPath('data.content.published_at', fn ($value) => is_string($value) && $value !== '');

        $content->refresh();
        $this->assertSame(EditorialContentStatus::Published, $content->status);
        $this->assertNotNull($content->published_at);
        $this->assertSame($chief->id, $content->published_by_user_id);

        $this->assertDatabaseCount('editorial_workflow_events', 2);
        $this->assertDatabaseHas('editorial_workflow_events', [
            'content_id' => $content->id,
            'from_status' => EditorialContentStatus::Draft->value,
            'to_status' => EditorialContentStatus::PendingReview->value,
            'actor_user_id' => $chief->id,
            'note' => 'Pronto per revisione',
        ]);
        $this->assertDatabaseHas('editorial_workflow_events', [
            'content_id' => $content->id,
            'from_status' => EditorialContentStatus::PendingReview->value,
            'to_status' => EditorialContentStatus::Published->value,
            'actor_user_id' => $chief->id,
            'note' => 'Approvato e pubblicato',
        ]);
    }

    public function test_editor_cannot_publish(): void
    {
        $editor = $this->userWithRole('editor');
        Sanctum::actingAs($editor);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'status' => EditorialContentStatus::PendingReview,
        ]);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::Published->value,
        ])
            ->assertForbidden();

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'status' => EditorialContentStatus::Draft,
        ]);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::Published->value,
        ])
            ->assertForbidden();
    }

    public function test_chief_editor_can_publish_from_pending_review(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'status' => EditorialContentStatus::PendingReview,
        ]);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::Published->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.content.status', 'published');
    }

    public function test_stale_if_match_returns_version_conflict_on_transition(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'status' => EditorialContentStatus::Draft,
        ]);

        $staleTimestamp = $content->updated_at?->copy()->subHour()->toIso8601String();

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::PendingReview->value,
        ], [
            'If-Match' => $staleTimestamp,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'VERSION_CONFLICT')
            ->assertJsonPath('error.details.current_updated_at', $content->updated_at?->toIso8601String());

        $this->assertDatabaseCount('editorial_workflow_events', 0);
    }

    public function test_stale_if_match_returns_version_conflict_on_patch(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'title' => 'Titolo originale',
        ]);

        $staleTimestamp = $content->updated_at?->copy()->subHour()->toIso8601String();

        $this->patchJson('/api/v1/admin/editorial/contents/'.$content->uuid, [
            'title' => 'Titolo aggiornato',
        ], [
            'If-Match' => $staleTimestamp,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'VERSION_CONFLICT');

        $content->refresh();
        $this->assertSame('Titolo originale', $content->title);
    }

    public function test_review_queue_lists_pending_review_items(): void
    {
        $reviewer = $this->userWithRole('reviewer');
        Sanctum::actingAs($reviewer);

        $pending = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'title' => 'In revisione',
            'status' => EditorialContentStatus::PendingReview,
        ]);

        EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'title' => 'Bozza',
            'status' => EditorialContentStatus::Draft,
        ]);

        EditorialContent::factory()->published()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'title' => 'Pubblicato',
        ]);

        $this->getJson('/api/v1/admin/editorial/review-queue')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.content.uuid', $pending->uuid)
            ->assertJsonPath('data.items.0.content.status', 'pending_review');
    }

    public function test_structure_content_pending_review_appears_in_review_queue_with_moderation_entry(): void
    {
        $editor = $this->userWithRole('editor');
        Sanctum::actingAs($editor);

        $company = Company::factory()->create(['sector_id' => $this->sector->id]);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'author_type' => EditorialAuthorType::Company,
            'company_id' => $company->id,
            'status' => EditorialContentStatus::Draft,
        ]);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::PendingReview->value,
        ])
            ->assertOk();

        $this->assertDatabaseHas('editorial_moderation_queue', [
            'content_id' => $content->id,
            'company_id' => $company->id,
            'status' => EditorialModerationStatus::Pending->value,
            'submitted_by_user_id' => $editor->id,
        ]);

        $reviewer = $this->userWithRole('reviewer');
        Sanctum::actingAs($reviewer);

        $this->getJson('/api/v1/admin/editorial/review-queue?structure_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.content.uuid', $content->uuid)
            ->assertJsonPath('data.items.0.moderation.status', 'pending')
            ->assertJsonPath('data.items.0.moderation.company_id', $company->id);
    }

    public function test_reviewer_can_reject_pending_review_content(): void
    {
        $reviewer = $this->userWithRole('reviewer');
        Sanctum::actingAs($reviewer);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'status' => EditorialContentStatus::PendingReview,
        ]);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::Rejected->value,
            'note' => 'Serve revisione YMYL',
        ])
            ->assertOk()
            ->assertJsonPath('data.content.status', 'rejected');

        $this->assertDatabaseHas('editorial_workflow_events', [
            'content_id' => $content->id,
            'to_status' => EditorialContentStatus::Rejected->value,
            'note' => 'Serve revisione YMYL',
        ]);
    }

    public function test_invalid_transition_returns_unprocessable(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $content = EditorialContent::factory()->published()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
        ]);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::Draft->value,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_TRANSITION');
    }

    public function test_superadmin_can_direct_publish_from_draft(): void
    {
        $admin = User::factory()->create(['user_type' => UserType::Superadmin]);
        Sanctum::actingAs($admin);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'status' => EditorialContentStatus::Draft,
        ]);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::Published->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.content.status', 'published');
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role->id, ['company_id' => null]);

        return $user;
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
