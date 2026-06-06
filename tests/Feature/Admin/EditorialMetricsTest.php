<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\EditorialContentStatus;
use App\Enums\EditorialContentType;
use App\Enums\EditorialIndexQueueAction;
use App\Enums\EditorialIndexQueueStatus;
use App\Enums\EditorialModerationStatus;
use App\Enums\EditorialSeoGenerationStatus;
use App\Enums\LeadStatus;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\EditorialContent;
use App\Models\EditorialIndexQueue;
use App\Models\EditorialModerationQueue;
use App\Models\EditorialRubric;
use App\Models\EditorialSeoGeneration;
use App\Models\Lead;
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

class EditorialMetricsTest extends TestCase
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
        $this->seed(EditorialPermissionSeeder::class);

        $this->guideRubric = EditorialRubric::query()->where('slug', 'guide')->firstOrFail();

        RateLimiter::clear('admin');
    }

    public function test_metrics_requires_admin_editorial_access(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/editorial/metrics')
            ->assertForbidden();
    }

    public function test_metrics_returns_editorial_aggregates(): void
    {
        $chief = $this->userWithRole('chief_editor');
        Sanctum::actingAs($chief);

        EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->guideRubric->id,
            'rubric_slug' => $this->guideRubric->slug,
            'status' => EditorialContentStatus::Draft,
            'content_type' => EditorialContentType::Article,
        ]);

        EditorialContent::factory()->published()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->guideRubric->id,
            'rubric_slug' => $this->guideRubric->slug,
            'content_type' => EditorialContentType::Article,
            'published_at' => now()->subDays(3),
        ]);

        EditorialContent::factory()->published()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->guideRubric->id,
            'rubric_slug' => $this->guideRubric->slug,
            'content_type' => EditorialContentType::Story,
            'published_at' => now()->subDays(45),
        ]);

        $pendingContent = EditorialContent::factory()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->guideRubric->id,
            'rubric_slug' => $this->guideRubric->slug,
            'status' => EditorialContentStatus::PendingReview,
        ]);

        $company = Company::query()->create([
            'sector_id' => $this->sector->id,
            'organization_name' => 'Casa Serena RSA',
            'legal_name' => 'Casa Serena RSA S.r.l.',
            'city' => 'Milano',
            'vetting_status' => VettingStatus::Approved,
        ]);

        EditorialModerationQueue::query()->create([
            'content_id' => $pendingContent->id,
            'company_id' => $company->id,
            'status' => EditorialModerationStatus::Pending,
            'submitted_at' => now(),
        ]);

        $published = EditorialContent::factory()->published()->create([
            'sector_id' => $this->sector->id,
            'rubric_id' => $this->guideRubric->id,
            'rubric_slug' => $this->guideRubric->slug,
            'content_type' => EditorialContentType::Interview,
            'published_at' => now()->subDay(),
        ]);

        EditorialSeoGeneration::query()->create([
            'content_id' => $published->id,
            'seo_pack' => ['seo_score' => 82],
            'score' => 82,
            'status' => EditorialSeoGenerationStatus::Approved,
            'prompt_version' => 'editorial-seo-v1',
        ]);

        EditorialIndexQueue::query()->create([
            'editorial_content_id' => $published->id,
            'action' => EditorialIndexQueueAction::Index,
            'status' => EditorialIndexQueueStatus::Pending,
        ]);

        Lead::query()->create([
            'sector_id' => $this->sector->id,
            'status' => LeadStatus::Processing,
            'contact_name' => 'Mario Rossi',
            'contact_email' => 'mario@example.com',
            'payload' => ['query' => 'badante milano'],
        ]);

        Lead::query()->create([
            'sector_id' => $this->sector->id,
            'status' => LeadStatus::Processing,
            'contact_name' => 'Anonimo',
            'payload' => ['query' => 'rsa torino'],
        ]);

        $response = $this->getJson('/api/v1/admin/editorial/metrics')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.moderation_backlog', 1)
            ->assertJsonPath('data.published_last_30_days', 2)
            ->assertJsonPath('data.index_queue_pending', 1)
            ->assertJsonPath('data.searches_count', 2)
            ->assertJsonPath('data.leads_with_email', 1);

        $contentsByStatus = $response->json('data.contents_by_status');
        $this->assertSame(1, $contentsByStatus['draft']);
        $this->assertSame(1, $contentsByStatus['pending_review']);
        $this->assertSame(3, $contentsByStatus['published']);

        $histogram = collect($response->json('data.seo_score_histogram'));
        $bucket8090 = $histogram->firstWhere('label', '80–89');
        $this->assertSame(1, $bucket8090['count']);

        $topTypes = collect($response->json('data.top_published_by_type'));
        $this->assertTrue($topTypes->contains(fn (array $row): bool => $row['type'] === 'article' && $row['count'] === 1));
        $this->assertCount(3, $topTypes);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->roles()->attach($role->id, ['company_id' => null]);

        return $user;
    }
}
