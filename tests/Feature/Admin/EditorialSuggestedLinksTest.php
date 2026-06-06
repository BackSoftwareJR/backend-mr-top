<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\EditorialContentLinkType;
use App\Enums\EditorialContentStatus;
use App\Enums\UserType;
use App\Jobs\SuggestInternalLinksJob;
use App\Models\EditorialContent;
use App\Models\EditorialContentLink;
use App\Models\EditorialRubric;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorialSuggestedLinksTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/admin/editorial/contents/%s/suggested-links';

    private Sector $sector;

    private EditorialRubric $guideRubric;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->seed(\Database\Seeders\EditorialRubricSeeder::class);
        $this->seedEditorialPermissions();

        $this->guideRubric = EditorialRubric::query()->where('slug', 'guide')->firstOrFail();

        $this->editor = User::factory()->create([
            'user_type' => UserType::Superadmin,
        ]);
        $this->editor->roles()->attach(
            Role::query()->where('name', 'chief_editor')->firstOrFail()->id,
        );
    }

    public function test_admin_can_fetch_suggested_links_with_scores(): void
    {
        Sanctum::actingAs($this->editor);

        $source = $this->createPublishedContent([
            'title' => 'RSA Milano: guida completa',
            'slug' => 'rsa-milano-guida',
            'tags' => ['rsa', 'milano', 'strutture'],
            'seo_pack' => [
                'primary_keyword' => 'rsa milano',
                'secondary_keywords' => ['residenza sanitaria', 'assistenza anziani'],
            ],
        ]);

        $this->createPublishedContent([
            'title' => 'Come scegliere una RSA a Milano',
            'slug' => 'scegliere-rsa-milano',
            'tags' => ['rsa', 'milano', 'scelta'],
        ]);

        $this->createPublishedContent([
            'title' => 'Costi badante convivente',
            'slug' => 'costi-badante-convivente',
            'tags' => ['badante', 'costi'],
        ]);

        $response = $this->getJson(sprintf(self::ENDPOINT, $source->uuid));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'suggestions' => [
                        ['target_uuid', 'title', 'slug', 'score', 'link_type'],
                    ],
                ],
            ]);

        $suggestions = $response->json('data.suggestions');
        $this->assertNotEmpty($suggestions);
        $this->assertLessThanOrEqual(5, count($suggestions));
        $this->assertSame('suggested', $suggestions[0]['link_type']);
        $this->assertGreaterThan(0, $suggestions[0]['score']);

        $this->assertDatabaseHas('editorial_content_links', [
            'source_content_id' => $source->id,
            'link_type' => EditorialContentLinkType::Suggested->value,
        ]);
    }

    public function test_suggested_links_endpoint_returns_stored_suggestions(): void
    {
        Sanctum::actingAs($this->editor);

        $source = $this->createPublishedContent(['title' => 'Articolo sorgente']);
        $target = $this->createPublishedContent(['title' => 'Articolo correlato RSA']);

        EditorialContentLink::query()->create([
            'source_content_id' => $source->id,
            'target_content_id' => $target->id,
            'link_type' => EditorialContentLinkType::Suggested,
            'relevance_score' => 0.8125,
        ]);

        $response = $this->getJson(sprintf(self::ENDPOINT, $source->uuid));

        $response->assertOk()
            ->assertJsonPath('data.suggestions.0.target_uuid', $target->uuid)
            ->assertJsonPath('data.suggestions.0.score', 0.8125);
    }

    public function test_publish_dispatches_suggest_internal_links_job(): void
    {
        Queue::fake();

        Sanctum::actingAs($this->editor);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->guideRubric->id,
            'rubric_slug' => $this->guideRubric->slug,
            'status' => EditorialContentStatus::Draft,
            'seo_pack' => ['approved' => true, 'score' => 85],
        ]);

        config([
            'editorial.seo.require_seo_approval' => false,
        ]);

        $this->postJson("/api/v1/admin/editorial/contents/{$content->uuid}/transition", [
            'to_status' => EditorialContentStatus::Published->value,
        ])->assertOk();

        Queue::assertPushed(SuggestInternalLinksJob::class, function (SuggestInternalLinksJob $job) use ($content): bool {
            return $job->contentId === $content->id;
        });
    }

    public function test_guest_cannot_access_suggested_links(): void
    {
        $content = $this->createPublishedContent(['title' => 'Test']);

        $this->getJson(sprintf(self::ENDPOINT, $content->uuid))
            ->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPublishedContent(array $overrides = []): EditorialContent
    {
        return EditorialContent::factory()->published()->create(array_merge([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->guideRubric->id,
            'rubric_slug' => $this->guideRubric->slug,
            'noindex' => false,
        ], $overrides));
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
