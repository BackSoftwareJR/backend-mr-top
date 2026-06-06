<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentStatus;
use App\Enums\UserType;
use App\Jobs\GenerateEditorialSeoJob;
use App\Models\EditorialContent;
use App\Models\EditorialRubric;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\EditorialRubricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgentEditorialWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-agent-webhook-secret';

    private Sector $sector;

    private EditorialRubric $rubric;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'editorial.agent_webhook_secret' => self::WEBHOOK_SECRET,
            'editorial.agent_user_email' => 'agent@wenando.system',
        ]);

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->seed(EditorialRubricSeeder::class);
        $this->seedEditorialPermissions();
        $this->createAgentSystemUser();

        $this->rubric = EditorialRubric::query()->where('slug', 'guide')->firstOrFail();
    }

    public function test_valid_signature_creates_agent_draft(): void
    {
        $payload = $this->validPayload();

        $response = $this->signedPost($payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.content.status', 'draft')
            ->assertJsonPath('data.content.idempotent', false);

        $uuid = $response->json('data.content.uuid');
        $this->assertNotEmpty($uuid);

        $this->assertDatabaseHas('editorial_contents', [
            'uuid' => $uuid,
            'author_type' => EditorialAuthorType::Agent->value,
            'status' => EditorialContentStatus::Draft->value,
            'title' => 'Guida agent webhook',
        ]);

        $content = EditorialContent::query()->where('uuid', $uuid)->firstOrFail();

        $this->assertDatabaseHas('editorial_workflow_events', [
            'content_id' => $content->id,
            'from_status' => null,
            'to_status' => EditorialContentStatus::Draft->value,
            'note' => 'agent ingest',
        ]);
    }

    public function test_invalid_signature_returns_401(): void
    {
        $payload = $this->validPayload();
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/v1/webhooks/editorial/agent-draft',
            [],
            [],
            [],
            [
                'HTTP_X-Editorial-Agent-Signature' => 'invalid-signature',
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ],
            $body,
        )
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'WEBHOOK_UNAUTHORIZED');
    }

    public function test_external_ref_is_idempotent(): void
    {
        $payload = $this->validPayload([
            'external_ref' => 'agent-run-001',
        ]);

        $first = $this->signedPost($payload)
            ->assertCreated()
            ->assertJsonPath('data.content.idempotent', false);

        $uuid = $first->json('data.content.uuid');

        $this->signedPost($payload)
            ->assertOk()
            ->assertJsonPath('data.content.uuid', $uuid)
            ->assertJsonPath('data.content.idempotent', true);

        $this->assertSame(1, EditorialContent::query()->where('external_ref', 'agent-run-001')->count());
    }

    public function test_auto_submit_review_transitions_to_pending_review(): void
    {
        Queue::fake();

        $payload = $this->validPayload([
            'auto_submit_review' => true,
        ]);

        $response = $this->signedPost($payload)
            ->assertCreated()
            ->assertJsonPath('data.content.status', 'pending_review');

        $content = EditorialContent::query()
            ->where('uuid', $response->json('data.content.uuid'))
            ->firstOrFail();

        $this->assertSame(EditorialContentStatus::PendingReview, $content->status);
        $this->assertNull($content->published_at);

        Queue::assertPushed(
            GenerateEditorialSeoJob::class,
            fn (GenerateEditorialSeoJob $job): bool => $job->contentId === $content->id,
        );
    }

    public function test_cannot_publish_via_webhook(): void
    {
        $payload = $this->validPayload([
            'status' => 'published',
        ]);

        $this->signedPost($payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $payload = $this->validPayload([
            'auto_publish' => true,
        ]);

        $this->signedPost($payload)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $payload = $this->validPayload([
            'auto_submit_review' => true,
        ]);

        $response = $this->signedPost($payload)->assertCreated();
        $uuid = $response->json('data.content.uuid');

        $content = EditorialContent::query()->where('uuid', $uuid)->firstOrFail();
        $this->assertSame(EditorialContentStatus::PendingReview, $content->status);
        $this->assertNull($content->published_at);
        $this->assertNull($content->published_by_user_id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Guida agent webhook',
            'type' => 'article',
            'rubric_slug' => $this->rubric->slug,
            'body_blocks' => [
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
                        'html' => '<p>Contenuto generato da agent.</p>',
                    ],
                ],
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signedPost(array $payload): \Illuminate\Testing\TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        return $this->call(
            'POST',
            '/api/v1/webhooks/editorial/agent-draft',
            [],
            [],
            [],
            [
                'HTTP_X-Editorial-Agent-Signature' => $signature,
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ],
            $body,
        );
    }

    private function createAgentSystemUser(): void
    {
        $user = User::factory()->create([
            'email' => 'agent@wenando.system',
            'user_type' => UserType::Superadmin,
        ]);

        $role = Role::query()->where('name', 'editorial_agent')->firstOrFail();
        $user->roles()->attach($role->id, ['company_id' => null]);
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
            'editorial.agent',
        ];

        foreach ($permissions as $name) {
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $byName = static fn (array $names) => Permission::query()->whereIn('name', $names)->pluck('id');

        $roles = [
            'editorial_agent' => $byName(['editorial.agent']),
        ];

        foreach ($roles as $roleName => $permissionIds) {
            $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
