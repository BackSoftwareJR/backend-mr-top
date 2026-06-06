<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\EditorialContentStatus;
use App\Enums\EditorialSeoGenerationStatus;
use App\Enums\UserType;
use App\Jobs\GenerateEditorialSeoJob;
use App\Models\EditorialContent;
use App\Models\EditorialRubric;
use App\Models\EditorialSeoGeneration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\EditorialRubricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorialSeoTest extends TestCase
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

        config([
            'services.groq.api_key' => null,
            'services.groq.model' => 'llama-3.3-70b-versatile',
            'services.groq.base_url' => 'https://api.groq.com/openai/v1',
            'editorial.seo.min_score' => 70,
            'editorial.seo.require_seo_approval' => true,
            'editorial.seo.superadmin_bypass' => false,
        ]);
    }

    public function test_groq_success_stores_pending_generation(): void
    {
        config(['services.groq.api_key' => 'test-groq-key']);

        $groqSeoPack = [
            'seo_title' => 'Badante convivente: costi reali e trappole da evitare nel 2026',
            'seo_description' => 'Rete, contributi e costi nascosti della badante convivente spiegati con chiarezza. Checklist anti-truffe e domande da fare prima di firmare.',
            'excerpt' => 'Guida completa ai costi della badante convivente in Italia.',
            'og_title' => 'Badante convivente: costi reali',
            'og_description' => 'Scopri retta, contributi INPS e red flags contrattuali.',
            'primary_keyword' => 'costo badante convivente',
            'secondary_keywords' => ['badante convivente', 'contributi INPS'],
            'suggested_tags' => ['badante', 'costi', 'assistenza'],
            'json_ld_hints' => ['schema_type' => 'Article', 'faq_items' => null],
        ];

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode($groqSeoPack, JSON_THROW_ON_ERROR),
                    ],
                ]],
            ], 200),
        ]);

        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'title' => 'Quanto costa una badante convivente',
            'word_count' => 850,
        ]);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/seo/regenerate')
            ->assertCreated()
            ->assertJsonPath('data.generation.status', 'pending')
            ->assertJsonPath('data.generation.seo_pack.seo_title', $groqSeoPack['seo_title'])
            ->assertJsonPath('data.generation.seo_pack.generated_by', 'groq')
            ->assertJsonPath('data.generation.seo_pack.seo_score', fn ($value) => is_int($value) && $value >= 0);

        $this->assertDatabaseHas('editorial_seo_generations', [
            'content_id' => $content->id,
            'status' => EditorialSeoGenerationStatus::Pending->value,
            'groq_model' => config('editorial.seo.groq_model'),
        ]);

        Http::assertSentCount(1);
    }

    public function test_approve_applies_seo_fields_to_content(): void
    {
        $reviewer = $this->userWithRole('reviewer');
        Sanctum::actingAs($reviewer);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'status' => EditorialContentStatus::PendingReview,
            'excerpt' => null,
        ]);

        $seoPack = $this->sampleSeoPack(82);

        EditorialSeoGeneration::query()->create([
            'content_id' => $content->id,
            'seo_pack' => $seoPack,
            'score' => 82,
            'status' => EditorialSeoGenerationStatus::Pending,
            'groq_model' => 'fallback',
            'prompt_version' => 'editorial-seo-v1',
        ]);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/seo/approve')
            ->assertOk()
            ->assertJsonPath('data.content.seo_pack.approved', true)
            ->assertJsonPath('data.content.seo_pack.seo_title', $seoPack['seo_title'])
            ->assertJsonPath('data.content.excerpt', $seoPack['excerpt']);

        $content->refresh();
        $this->assertTrue($content->seo_pack['approved'] ?? false);
        $this->assertSame($seoPack['seo_title'], $content->seo_pack['seo_title']);

        $this->assertDatabaseHas('editorial_seo_generations', [
            'content_id' => $content->id,
            'status' => EditorialSeoGenerationStatus::Approved->value,
            'reviewed_by_user_id' => $reviewer->id,
        ]);

        $this->assertDatabaseHas('editorial_content_seo_audits', [
            'editorial_content_id' => $content->id,
            'approved' => true,
            'seo_score' => 82,
        ]);
    }

    public function test_publish_blocked_without_approved_seo(): void
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
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SEO_NOT_APPROVED');

        $content->refresh();
        $this->assertSame(EditorialContentStatus::PendingReview, $content->status);
    }

    public function test_publish_succeeds_after_seo_approve(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'status' => EditorialContentStatus::PendingReview,
        ]);

        EditorialSeoGeneration::query()->create([
            'content_id' => $content->id,
            'seo_pack' => $this->sampleSeoPack(85),
            'score' => 85,
            'status' => EditorialSeoGenerationStatus::Pending,
            'prompt_version' => 'editorial-seo-v1',
        ]);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/seo/approve')
            ->assertOk();

        $content->refresh();

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::Published->value,
        ], [
            'If-Match' => $content->updated_at?->toIso8601String(),
        ])
            ->assertOk()
            ->assertJsonPath('data.content.status', 'published');
    }

    public function test_fallback_generation_when_groq_not_configured(): void
    {
        $reviewer = $this->userWithRole('reviewer');
        Sanctum::actingAs($reviewer);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'title' => 'Guida alla badante convivente in Lombardia',
            'excerpt' => 'Tutto quello che serve sapere sui costi e i contributi.',
            'word_count' => 500,
        ]);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/seo/regenerate')
            ->assertCreated()
            ->assertJsonPath('data.generation.seo_pack.generated_by', 'fallback')
            ->assertJsonPath('data.generation.status', 'pending')
            ->assertJsonStructure([
                'data' => [
                    'generation' => [
                        'seo_pack' => [
                            'seo_title',
                            'seo_description',
                            'excerpt',
                            'primary_keyword',
                            'secondary_keywords',
                            'seo_score',
                            'seo_score_breakdown',
                        ],
                    ],
                ],
            ]);

        Http::assertNothingSent();
    }

    public function test_pending_review_transition_dispatches_seo_job(): void
    {
        Queue::fake();

        $editor = $this->userWithRole('editor');
        Sanctum::actingAs($editor);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
            'status' => EditorialContentStatus::Draft,
        ]);

        $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/transition', [
            'to_status' => EditorialContentStatus::PendingReview->value,
        ])
            ->assertOk();

        Queue::assertPushed(GenerateEditorialSeoJob::class, fn (GenerateEditorialSeoJob $job): bool => $job->contentId === $content->id);
    }

    public function test_get_seo_returns_latest_and_history(): void
    {
        $reviewer = $this->userWithRole('reviewer');
        Sanctum::actingAs($reviewer);

        $content = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->rubric->id,
            'rubric_slug' => $this->rubric->slug,
        ]);

        EditorialSeoGeneration::query()->create([
            'content_id' => $content->id,
            'seo_pack' => $this->sampleSeoPack(75),
            'score' => 75,
            'status' => EditorialSeoGenerationStatus::Rejected,
            'prompt_version' => 'editorial-seo-v1',
            'created_at' => now()->subHour(),
        ]);

        $latest = EditorialSeoGeneration::query()->create([
            'content_id' => $content->id,
            'seo_pack' => $this->sampleSeoPack(80),
            'score' => 80,
            'status' => EditorialSeoGenerationStatus::Pending,
            'prompt_version' => 'editorial-seo-v1',
        ]);

        $this->getJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/seo')
            ->assertOk()
            ->assertJsonPath('data.latest.id', $latest->id)
            ->assertJsonCount(2, 'data.history')
            ->assertJsonPath('data.groq_configured', false);
    }

    public function test_superadmin_can_publish_with_bypass_flag(): void
    {
        config(['editorial.seo.superadmin_bypass' => true]);

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

    /**
     * @return array<string, mixed>
     */
    private function sampleSeoPack(int $score): array
    {
        return [
            'version' => 3,
            'generated_by' => 'fallback',
            'approved' => false,
            'seo_title' => 'Badante convivente: costi reali e trappole da evitare nel 2026',
            'seo_description' => 'Rete, contributi e costi nascosti della badante convivente spiegati con chiarezza. Checklist anti-truffe e domande da fare prima di firmare un contratto.',
            'excerpt' => 'Guida completa ai costi della badante convivente in Italia con checklist anti-truffe.',
            'og_title' => 'Badante convivente: costi reali',
            'og_description' => 'Scopri retta, contributi INPS e red flags contrattuali.',
            'primary_keyword' => 'costo badante convivente',
            'secondary_keywords' => ['badante convivente', 'contributi INPS'],
            'suggested_tags' => ['badante', 'costi'],
            'json_ld_hints' => ['schema_type' => 'Article', 'faq_items' => null],
            'seo_score' => $score,
            'seo_score_breakdown' => [
                'title_length' => 9,
                'description_length' => 9,
                'keyword_in_title' => 8,
                'heading_structure' => 7,
                'internal_links' => 4,
                'faq_present' => 5,
                'ymyl_disclaimer' => 6,
                'readability' => 7,
                'geo_excerpt_quality' => 8,
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
