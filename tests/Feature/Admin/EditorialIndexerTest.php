<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\EditorialContentStatus;
use App\Enums\EditorialContentType;
use App\Enums\EditorialIndexQueueAction;
use App\Enums\EditorialIndexQueueStatus;
use App\Enums\EditorialSeoGenerationStatus;
use App\Models\EditorialContent;
use App\Models\EditorialIndexRule;
use App\Models\EditorialRubric;
use App\Models\EditorialSearchDocument;
use App\Models\EditorialSeoGeneration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\EditorialPermissionSeeder;
use Database\Seeders\EditorialRubricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorialIndexerTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    private EditorialRubric $guideRubric;

    private EditorialRubric $costiRubric;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->seed(EditorialRubricSeeder::class);
        $this->seed(EditorialPermissionSeeder::class);

        $this->guideRubric = EditorialRubric::query()->where('slug', 'guide')->firstOrFail();
        $this->costiRubric = EditorialRubric::query()->where('slug', 'costi')->firstOrFail();

        config([
            'editorial.seo.min_score' => 70,
            'editorial.seo.require_seo_approval' => true,
            'editorial.seo.superadmin_bypass' => false,
        ]);

        RateLimiter::clear('search-editorial');
        RateLimiter::clear('admin');
    }

    public function test_publish_indexes_document(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $content = $this->createDraftContent([
            'title' => 'Badante convivente: costi e trappole',
            'body_blocks' => [
                [
                    'type' => 'paragraph',
                    'data' => ['html' => '<p>Guida completa sui costi della badante convivente in Italia.</p>'],
                ],
            ],
        ]);

        $this->transitionToPublished($content, $chief);

        $this->assertDatabaseHas('editorial_search_documents', [
            'content_id' => $content->id,
            'title' => 'Badante convivente: costi e trappole',
            'rubric' => 'guide',
        ]);

        $document = EditorialSearchDocument::query()->where('content_id', $content->id)->first();
        $this->assertNotNull($document);
        $this->assertStringContainsString('badante convivente', mb_strtolower((string) $document->body_text));
        $this->assertNotNull($document->indexed_at);

        $this->assertDatabaseHas('editorial_index_queue', [
            'editorial_content_id' => $content->id,
            'action' => EditorialIndexQueueAction::Index->value,
            'status' => EditorialIndexQueueStatus::Completed->value,
        ]);
    }

    public function test_archive_removes_document(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $content = $this->createDraftContent(['title' => 'Articolo da archiviare']);
        $this->transitionToPublished($content, $chief);

        $this->assertDatabaseHas('editorial_search_documents', ['content_id' => $content->id]);

        $content->refresh();

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::Archived->value,
        ], [
            'If-Match' => $content->updated_at?->toIso8601String(),
        ])->assertOk();

        $this->assertDatabaseMissing('editorial_search_documents', ['content_id' => $content->id]);
        $this->assertDatabaseHas('editorial_index_queue', [
            'editorial_content_id' => $content->id,
            'action' => EditorialIndexQueueAction::Remove->value,
            'status' => EditorialIndexQueueStatus::Completed->value,
        ]);
    }

    public function test_noindex_content_not_searchable(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $content = $this->createDraftContent([
            'title' => 'Contenuto nascosto badante privata',
            'noindex' => true,
        ]);

        $this->transitionToPublished($content, $chief);

        $this->assertDatabaseMissing('editorial_search_documents', ['content_id' => $content->id]);

        $this->getJson('/api/v1/b2c/search/editorial?q=badante+privata')
            ->assertOk()
            ->assertJsonPath('data.items', []);
    }

    public function test_reindex_endpoint_enqueues(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $content = $this->createDraftContent(['title' => 'Guida RSA Lombardia']);
        $this->transitionToPublished($content, $chief);

        EditorialSearchDocument::query()->where('content_id', $content->id)->delete();

        $this->postJson('/api/v1/admin/editorial/reindex', [
            'content_uuid' => $content->uuid,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.queued', 1);

        $this->assertDatabaseHas('editorial_search_documents', ['content_id' => $content->id]);
        $this->assertDatabaseHas('editorial_index_queue', [
            'editorial_content_id' => $content->id,
            'action' => EditorialIndexQueueAction::Reindex->value,
            'status' => EditorialIndexQueueStatus::Completed->value,
        ]);
    }

    public function test_search_api_returns_ranked_results(): void
    {
        $this->indexPublishedContent([
            'title' => 'Costi RSA Lombardia 2026',
            'slug' => 'costi-rsa-lombardia-2026',
            'rubric_id' => $this->costiRubric->id,
            'rubric_slug' => 'costi',
            'featured' => false,
            'published_at' => now()->subDays(60),
            'body_blocks' => [
                ['type' => 'paragraph', 'data' => ['html' => '<p>Tariffe RSA in Lombardia.</p>']],
            ],
        ]);

        $this->indexPublishedContent([
            'title' => 'Badante convivente: guida completa',
            'slug' => 'badante-convivente-guida',
            'rubric_id' => $this->guideRubric->id,
            'rubric_slug' => 'guide',
            'featured' => true,
            'published_at' => now()->subDay(),
            'body_blocks' => [
                ['type' => 'paragraph', 'data' => ['html' => '<p>Tutto sulla badante convivente e i costi mensili.</p>']],
            ],
        ]);

        $response = $this->getJson('/api/v1/b2c/search/editorial?q=badante&limit=5');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        ['id', 'type', 'title', 'description', 'category', 'readMinutes', 'url'],
                    ],
                ],
            ]);

        $items = $response->json('data.items');
        $this->assertNotEmpty($items);
        $this->assertSame('Badante convivente: guida completa', $items[0]['title']);
        $this->assertTrue($items[0]['featured'] ?? false);
    }

    public function test_patch_index_rule_excludes_rubric_from_search(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $this->indexPublishedContent([
            'title' => 'Quanto costa una badante',
            'slug' => 'quanto-costa-badante',
            'rubric_id' => $this->costiRubric->id,
            'rubric_slug' => 'costi',
            'body_blocks' => [
                ['type' => 'paragraph', 'data' => ['html' => '<p>Costi badante aggiornati.</p>']],
            ],
        ]);

        $this->getJson('/api/v1/b2c/search/editorial?q=badante&rubric=costi')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');

        $rule = EditorialIndexRule::factory()->create([
            'rubric_slug' => 'costi',
            'include_in_internal_search' => false,
            'include_in_sitemap' => true,
            'is_active' => true,
        ]);

        $this->patchJson('/api/v1/admin/editorial/index-rules/'.$rule->id, [
            'include_in_internal_search' => false,
        ])->assertOk();

        $this->postJson('/api/v1/admin/editorial/reindex', [
            'rubric_slug' => 'costi',
        ])->assertOk();

        $this->getJson('/api/v1/b2c/search/editorial?q=badante&rubric=costi')
            ->assertOk()
            ->assertJsonPath('data.items', []);
    }

    public function test_index_manage_requires_permission(): void
    {
        $editor = $this->userWithRole('editor');
        Sanctum::actingAs($editor);

        $this->getJson('/api/v1/admin/editorial/index-rules')
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createDraftContent(array $overrides = []): EditorialContent
    {
        return EditorialContent::factory()->create(array_merge([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->guideRubric->id,
            'rubric_slug' => $this->guideRubric->slug,
            'status' => EditorialContentStatus::Draft,
        ], $overrides));
    }

    private function transitionToPublished(EditorialContent $content, User $actor): void
    {
        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::PendingReview->value,
        ], [
            'If-Match' => $content->updated_at?->toIso8601String(),
        ])->assertOk();

        $content->refresh();
        $this->seedApprovedSeo($content, $actor);
        $content->refresh();

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::Published->value,
        ], [
            'If-Match' => $content->updated_at?->toIso8601String(),
        ])->assertOk();

        $content->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function indexPublishedContent(array $overrides = []): EditorialContent
    {
        $content = EditorialContent::factory()->published()->create(array_merge([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->guideRubric->id,
            'rubric_slug' => $this->guideRubric->slug,
            'content_type' => EditorialContentType::Article,
            'noindex' => false,
        ], $overrides));

        app(\App\Services\Editorial\EditorialSearchIndexer::class)->index($content);

        return $content->fresh(['rubric', 'heroMedia']);
    }

    private function seedApprovedSeo(EditorialContent $content, User $actor): void
    {
        $seoPack = [
            'version' => 3,
            'approved' => true,
            'approved_by_user_id' => $actor->id,
            'approved_at' => now()->toIso8601String(),
            'seo_title' => 'Badante convivente: costi reali e trappole da evitare nel 2026',
            'seo_description' => 'Rete, contributi e costi nascosti spiegati con chiarezza.',
            'excerpt' => 'Guida completa ai costi della badante convivente.',
            'primary_keyword' => 'costo badante convivente',
            'seo_score' => 85,
        ];

        EditorialSeoGeneration::query()->create([
            'content_id' => $content->id,
            'seo_pack' => $seoPack,
            'score' => 85,
            'status' => EditorialSeoGenerationStatus::Approved,
            'reviewed_by_user_id' => $actor->id,
            'reviewed_at' => now(),
            'prompt_version' => 'editorial-seo-v1',
        ]);

        $content->update(['seo_pack' => $seoPack]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role->id, ['company_id' => null]);

        return $user;
    }
}
