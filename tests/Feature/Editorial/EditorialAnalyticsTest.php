<?php

declare(strict_types=1);

namespace Tests\Feature\Editorial;

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\EditorialContent;
use App\Models\EditorialContentDailyStat;
use App\Models\EditorialMedia;
use App\Models\EditorialRubric;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\EditorialRubricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditorialAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    private EditorialRubric $guideRubric;

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

        $this->guideRubric = EditorialRubric::query()->where('slug', 'guide')->firstOrFail();

        RateLimiter::clear('admin');
    }

    public function test_published_article_page_view_is_tracked_on_magazine_show(): void
    {
        $content = $this->createPublishedContent([
            'slug' => 'guida-analytics',
        ]);

        $this->get('/magazine/guide/guida-analytics', [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        ])->assertOk();

        $stat = EditorialContentDailyStat::query()
            ->where('content_id', $content->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        $this->assertNotNull($stat);
        $this->assertSame(1, $stat->page_views);
        $this->assertSame(1, $stat->unique_visitors);
        $this->assertSame(0, $stat->bot_views);
    }

    public function test_repeat_view_same_day_increments_page_views_not_uniques(): void
    {
        $content = $this->createPublishedContent([
            'slug' => 'guida-repeat',
        ]);

        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        ];

        $this->get('/magazine/guide/guida-repeat', $headers)->assertOk();
        $this->get('/magazine/guide/guida-repeat', $headers)->assertOk();

        $stat = EditorialContentDailyStat::query()
            ->where('content_id', $content->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        $this->assertNotNull($stat);
        $this->assertSame(2, $stat->page_views);
        $this->assertSame(1, $stat->unique_visitors);
    }

    public function test_crawler_user_agent_is_not_counted_as_human_view(): void
    {
        $content = $this->createPublishedContent([
            'slug' => 'guida-bot',
        ]);

        $this->get('/magazine/guide/guida-bot', [
            'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ])->assertOk();

        $stat = EditorialContentDailyStat::query()
            ->where('content_id', $content->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        $this->assertNotNull($stat);
        $this->assertSame(0, $stat->page_views);
        $this->assertSame(0, $stat->unique_visitors);
        $this->assertSame(1, $stat->bot_views);
    }

    public function test_admin_sees_platform_analytics_overview(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $content = $this->createPublishedContent(['slug' => 'admin-top']);

        EditorialContentDailyStat::query()->create([
            'content_id' => $content->id,
            'date' => now()->toDateString(),
            'page_views' => 42,
            'unique_visitors' => 17,
            'bot_views' => 3,
        ]);

        $this->getJson('/api/v1/admin/editorial/analytics')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totals.page_views', 42)
            ->assertJsonPath('data.totals.unique_visitors', 17)
            ->assertJsonPath('data.totals.bot_views', 3)
            ->assertJsonPath('data.top_articles.0.uuid', $content->uuid)
            ->assertJsonPath('data.top_articles.0.page_views', 42)
            ->assertJsonStructure([
                'data' => [
                    'views_by_day',
                    'totals' => ['page_views', 'unique_visitors', 'bot_views'],
                    'top_articles',
                ],
            ]);
    }

    public function test_admin_content_analytics_drill_down(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        $content = $this->createPublishedContent(['slug' => 'drill-down']);

        EditorialContentDailyStat::query()->create([
            'content_id' => $content->id,
            'date' => now()->toDateString(),
            'page_views' => 9,
            'unique_visitors' => 4,
            'bot_views' => 0,
        ]);

        $this->getJson('/api/v1/admin/editorial/contents/'.$content->uuid.'/analytics')
            ->assertOk()
            ->assertJsonPath('data.content.uuid', $content->uuid)
            ->assertJsonPath('data.totals.page_views', 9)
            ->assertJsonPath('data.totals.unique_visitors', 4);
    }

    public function test_b2b_user_sees_only_own_company_stats(): void
    {
        [$partnerA, $companyA] = $this->partnerUser('Struttura A');
        [$partnerB, $companyB] = $this->partnerUser('Struttura B');

        $contentA = $this->createPublishedContent([
            'slug' => 'company-a-article',
            'company_id' => $companyA->id,
            'author_type' => EditorialAuthorType::Company,
        ]);

        $contentB = $this->createPublishedContent([
            'slug' => 'company-b-article',
            'company_id' => $companyB->id,
            'author_type' => EditorialAuthorType::Company,
        ]);

        EditorialContentDailyStat::query()->create([
            'content_id' => $contentA->id,
            'date' => now()->toDateString(),
            'page_views' => 30,
            'unique_visitors' => 10,
            'bot_views' => 0,
        ]);

        EditorialContentDailyStat::query()->create([
            'content_id' => $contentB->id,
            'date' => now()->toDateString(),
            'page_views' => 99,
            'unique_visitors' => 40,
            'bot_views' => 0,
        ]);

        Sanctum::actingAs($partnerA);

        $this->getJson('/api/v1/b2b/editorial/analytics')
            ->assertOk()
            ->assertJsonPath('data.totals.page_views', 30)
            ->assertJsonPath('data.totals.unique_visitors', 10)
            ->assertJsonPath('data.top_articles.0.uuid', $contentA->uuid)
            ->assertJsonMissing(['uuid' => $contentB->uuid]);
    }

    public function test_analytics_requires_admin_access(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/editorial/analytics')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPublishedContent(array $overrides = []): EditorialContent
    {
        $media = EditorialMedia::factory()->create();

        return EditorialContent::factory()
            ->published()
            ->withRubric($this->guideRubric)
            ->withHeroMedia($media)
            ->create(array_merge([
                'sector_id' => $this->sector->id,
                'rubric_id' => $this->guideRubric->id,
                'rubric_slug' => $this->guideRubric->slug,
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
