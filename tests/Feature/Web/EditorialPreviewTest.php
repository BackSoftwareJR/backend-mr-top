<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\EditorialContentStatus;
use App\Enums\UserType;
use App\Models\EditorialContent;
use App\Models\EditorialMedia;
use App\Models\EditorialRubric;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use App\Services\Editorial\EditorialPreviewService;
use Carbon\CarbonImmutable;
use Database\Seeders\EditorialRubricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorialPreviewTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    private EditorialRubric $guideRubric;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'editorial.site_url' => 'https://wenando.com',
            'editorial.preview_secret' => 'preview-test-secret',
            'editorial.preview_ttl_hours' => 24,
            'app.url' => 'https://wenando.com',
        ]);

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->seed(EditorialRubricSeeder::class);
        $this->guideRubric = EditorialRubric::query()->where('slug', 'guide')->firstOrFail();
    }

    public function test_valid_token_shows_draft_content_with_200(): void
    {
        $content = $this->createDraftContent([
            'title' => 'Bozza in anteprima segreta',
            'slug' => 'bozza-anteprima',
        ]);

        $token = app(EditorialPreviewService::class)->generate($content->uuid)['token'];

        $response = $this->get('/preview/editorial/'.$content->uuid.'?token='.urlencode($token));

        $response->assertOk();
        $response->assertSee('Bozza in anteprima segreta', false);
        $response->assertSee('<h1 class="editorial-title"', false);
    }

    public function test_expired_token_returns_403(): void
    {
        $content = $this->createDraftContent([
            'title' => 'Bozza scaduta',
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-01 12:00:00'));
        $token = app(EditorialPreviewService::class)->generate($content->uuid)['token'];
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-03 12:00:00'));

        $this->get('/preview/editorial/'.$content->uuid.'?token='.urlencode($token))
            ->assertForbidden();

        CarbonImmutable::setTestNow();
    }

    public function test_invalid_token_returns_403(): void
    {
        $content = $this->createDraftContent();

        $this->get('/preview/editorial/'.$content->uuid.'?token=not-a-valid-token')
            ->assertForbidden();
    }

    public function test_missing_token_returns_403(): void
    {
        $content = $this->createDraftContent();

        $this->get('/preview/editorial/'.$content->uuid)
            ->assertForbidden();
    }

    public function test_noindex_header_is_present(): void
    {
        $content = $this->createDraftContent();
        $token = app(EditorialPreviewService::class)->generate($content->uuid)['token'];

        $this->get('/preview/editorial/'.$content->uuid.'?token='.urlencode($token))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_archived_content_returns_403_even_with_valid_token(): void
    {
        $content = $this->createDraftContent([
            'status' => EditorialContentStatus::Archived,
        ]);

        $token = app(EditorialPreviewService::class)->generate($content->uuid)['token'];

        $this->get('/preview/editorial/'.$content->uuid.'?token='.urlencode($token))
            ->assertForbidden();
    }

    public function test_admin_can_generate_preview_token(): void
    {
        $this->seedEditorialPermissions();

        $admin = User::factory()->create(['user_type' => UserType::Superadmin]);
        Sanctum::actingAs($admin);

        $content = $this->createDraftContent([
            'title' => 'Token admin test',
        ]);

        $response = $this->postJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/preview-token');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['preview_url', 'expires_at'],
            ]);

        $previewUrl = (string) $response->json('data.preview_url');
        $this->assertStringStartsWith('https://wenando.com/preview/editorial/'.$content->uuid.'?token=', $previewUrl);

        parse_str((string) parse_url($previewUrl, PHP_URL_QUERY), $query);
        $this->assertNotEmpty($query['token'] ?? null);

        $this->get('/preview/editorial/'.$content->uuid.'?token='.urlencode((string) $query['token']))
            ->assertOk()
            ->assertSee('Token admin test', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createDraftContent(array $overrides = []): EditorialContent
    {
        $media = EditorialMedia::factory()->create();

        return EditorialContent::factory()
            ->withRubric($this->guideRubric)
            ->withHeroMedia($media)
            ->create(array_merge([
                'sector_id' => $this->sector->id,
                'rubric_id' => $this->guideRubric->id,
                'rubric_slug' => $this->guideRubric->slug,
                'status' => EditorialContentStatus::Draft,
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
