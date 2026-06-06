<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentStatus;
use App\Enums\EditorialNotificationType;
use App\Enums\EditorialSeoGenerationStatus;
use App\Enums\UserType;
use App\Mail\EditorialPendingReviewMail;
use App\Mail\EditorialReviewOutcomeMail;
use App\Mail\EditorialSeoReviewMail;
use App\Models\Company;
use App\Models\EditorialContent;
use App\Models\EditorialRubric;
use App\Models\EditorialSeoGeneration;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\EditorialRubricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorialNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    private EditorialRubric $rubric;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->seed(EditorialRubricSeeder::class);
        $this->seedEditorialPermissions();

        $this->rubric = EditorialRubric::query()->where('slug', 'guide')->firstOrFail();

        config([
            'editorial.seo.min_score' => 70,
            'editorial.seo.require_seo_approval' => true,
            'editorial.seo.superadmin_bypass' => false,
            'app.frontend_url' => 'https://app.wenando.test',
        ]);
    }

    public function test_b2b_submit_pending_review_notifies_reviewers(): void
    {
        $reviewer = $this->userWithRole('reviewer', 'reviewer@wenando.test');
        [$partner, $company] = $this->partnerUser('RSA Aurora');

        Sanctum::actingAs($partner);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'company_id' => $company->id,
            'author_type' => EditorialAuthorType::Company,
            'status' => EditorialContentStatus::Draft,
            'title' => 'Guida alla convivenza',
        ]);

        $this->postJson('/api/v1/b2b/editorial/contents/'.$content->uuid.'/submit', [], [
            'If-Match' => $content->updated_at?->toIso8601String(),
        ])->assertOk();

        Mail::assertQueued(EditorialPendingReviewMail::class, function (EditorialPendingReviewMail $mail) use ($reviewer): bool {
            return $mail->hasTo($reviewer->email)
                && str_contains($mail->contentTitle, 'Guida alla convivenza');
        });

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $reviewer->id,
            'type' => EditorialNotificationType::PendingReview->value,
        ]);

        $payload = Notification::query()
            ->where('notifiable_id', $reviewer->id)
            ->where('type', EditorialNotificationType::PendingReview->value)
            ->value('data');

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('content_uuid', $payload);
        $this->assertArrayNotHasKey('email', $payload);
    }

    public function test_review_outcome_notifies_company_users(): void
    {
        [$partner, $company] = $this->partnerUser('Casa Verde');
        $reviewer = $this->userWithRole('reviewer', 'reviewer@wenando.test');

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'company_id' => $company->id,
            'author_type' => EditorialAuthorType::Company,
            'status' => EditorialContentStatus::PendingReview,
            'title' => 'Servizi offerti',
        ]);

        Sanctum::actingAs($reviewer);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::Rejected->value,
            'note' => 'Manca disclaimer YMYL',
        ], [
            'If-Match' => $content->updated_at?->toIso8601String(),
        ])->assertOk();

        Mail::assertQueued(EditorialReviewOutcomeMail::class, function (EditorialReviewOutcomeMail $mail) use ($partner): bool {
            return $mail->hasTo($partner->email) && $mail->approved === false;
        });

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => Company::class,
            'notifiable_id' => $company->id,
            'type' => EditorialNotificationType::ReviewOutcome->value,
        ]);
    }

    public function test_seo_reject_notifies_reviewers_and_authors(): void
    {
        $reviewer = $this->userWithRole('reviewer', 'reviewer@wenando.test');
        [$partner, $company] = $this->partnerUser('Villa Sole');

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'company_id' => $company->id,
            'author_type' => EditorialAuthorType::Company,
            'status' => EditorialContentStatus::PendingReview,
        ]);

        EditorialSeoGeneration::query()->create([
            'content_id' => $content->id,
            'seo_pack' => ['seo_title' => 'Titolo', 'seo_description' => 'Descrizione'],
            'score' => 55,
            'status' => EditorialSeoGenerationStatus::Pending,
            'prompt_version' => 'editorial-seo-v1',
        ]);

        Sanctum::actingAs($reviewer);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/seo/reject', [
            'note' => 'Meta description troppo generica',
        ])->assertOk();

        Mail::assertQueued(EditorialSeoReviewMail::class, function (EditorialSeoReviewMail $mail) use ($reviewer): bool {
            return $mail->hasTo($reviewer->email) && $mail->reason === 'rejected';
        });

        Mail::assertQueued(EditorialSeoReviewMail::class, function (EditorialSeoReviewMail $mail) use ($partner): bool {
            return $mail->hasTo($partner->email);
        });
    }

    public function test_low_seo_score_on_generation_notifies_once(): void
    {
        $reviewer = $this->userWithRole('reviewer', 'reviewer@wenando.test');

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'status' => EditorialContentStatus::PendingReview,
        ]);

        $generation = EditorialSeoGeneration::query()->create([
            'content_id' => $content->id,
            'seo_pack' => ['seo_title' => 'Titolo', 'seo_description' => 'Descrizione'],
            'score' => 45,
            'status' => EditorialSeoGenerationStatus::Pending,
            'prompt_version' => 'editorial-seo-v1',
        ]);

        app(\App\Services\Editorial\EditorialNotificationService::class)
            ->notifySeoNeedsReview($content, $generation, 'low_score');

        app(\App\Services\Editorial\EditorialNotificationService::class)
            ->notifySeoNeedsReview($content, $generation, 'low_score');

        Mail::assertQueued(EditorialSeoReviewMail::class, 1);

        $this->assertSame(
            1,
            Notification::query()
                ->where('type', EditorialNotificationType::SeoNeedsReview->value)
                ->where('notifiable_id', $reviewer->id)
                ->count(),
        );
    }

    public function test_admin_editorial_notifications_api_returns_unread_count(): void
    {
        $reviewer = $this->userWithRole('reviewer', 'reviewer@wenando.test');

        Notification::query()->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $reviewer->id,
            'type' => EditorialNotificationType::PendingReview->value,
            'data' => [
                'dedupe_key' => 'test:1',
                'content_uuid' => 'abc-123',
                'content_title' => 'Titolo test',
                'message' => EditorialNotificationType::PendingReview->label(),
            ],
            'read_at' => null,
        ]);

        Sanctum::actingAs($reviewer);

        $this->getJson('/api/v1/admin/editorial/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonCount(1, 'data.notifications');
    }

    public function test_b2b_editorial_notifications_are_company_scoped(): void
    {
        [$partner, $company] = $this->partnerUser();

        Notification::query()->create([
            'notifiable_type' => Company::class,
            'notifiable_id' => $company->id,
            'type' => EditorialNotificationType::ReviewOutcome->value,
            'data' => [
                'dedupe_key' => 'test:2',
                'content_uuid' => 'def-456',
                'content_title' => 'Esito test',
                'outcome' => 'rejected',
                'message' => EditorialNotificationType::ReviewOutcome->label(),
            ],
            'read_at' => null,
        ]);

        Sanctum::actingAs($partner);

        $this->getJson('/api/v1/b2b/editorial/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.notifications.0.data.outcome', 'rejected');
    }

    private function userWithRole(string $roleName, ?string $email = null): User
    {
        $user = User::factory()->create([
            'email' => $email ?? fake()->unique()->safeEmail(),
        ]);
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role->id, ['company_id' => null]);

        return $user;
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
