<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\DataErasureRequestStatus;
use App\Enums\UserType;
use App\Jobs\ProcessDataErasureRequest;
use App\Models\DataErasureRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPrivacyErasureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'user_type' => UserType::Superadmin,
            'email' => 'admin@wenando.com',
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_superadmin_lists_pending_and_processing_erasure_requests(): void
    {
        $consumer = User::factory()->create([
            'user_type' => UserType::Consumer,
            'email' => 'pending@example.com',
            'name' => 'Pending User',
        ]);

        $processingUser = User::factory()->create([
            'user_type' => UserType::Consumer,
            'email' => 'processing@example.com',
        ]);

        DataErasureRequest::query()->create([
            'user_id' => $consumer->id,
            'status' => DataErasureRequestStatus::Pending,
            'reason' => 'Non uso più il servizio.',
            'requested_at' => now()->subHour(),
        ]);

        DataErasureRequest::query()->create([
            'user_id' => $processingUser->id,
            'status' => DataErasureRequestStatus::Processing,
            'requested_at' => now(),
        ]);

        DataErasureRequest::query()->create([
            'user_id' => User::factory()->create(['user_type' => UserType::Consumer])->id,
            'status' => DataErasureRequestStatus::Completed,
            'requested_at' => now()->subDay(),
            'processed_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/admin/privacy/erasure-requests')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pending_count', 1)
            ->assertJsonPath('data.processing_count', 1)
            ->assertJsonCount(2, 'data.erasure_requests')
            ->assertJsonStructure([
                'data' => [
                    'pending_count',
                    'processing_count',
                    'erasure_requests' => [
                        '*' => [
                            'id',
                            'status',
                            'reason',
                            'requested_at',
                            'user' => ['id', 'email', 'name'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_non_superadmin_cannot_access_erasure_queue(): void
    {
        $consumer = User::factory()->create(['user_type' => UserType::Consumer]);
        Sanctum::actingAs($consumer);

        $this->getJson('/api/v1/admin/privacy/erasure-requests')
            ->assertForbidden();
    }

    public function test_admin_approve_pending_request_runs_erasure_cascade(): void
    {
        $consumer = User::factory()->create([
            'user_type' => UserType::Consumer,
            'email' => 'cascade@example.com',
            'name' => 'Cascade User',
        ]);

        $erasureRequest = DataErasureRequest::query()->create([
            'user_id' => $consumer->id,
            'status' => DataErasureRequestStatus::Pending,
            'requested_at' => now(),
        ]);

        $this->patchJson("/api/v1/admin/privacy/erasure-requests/{$erasureRequest->id}", [
            'action' => 'approve',
            'notes' => 'Identità verificata.',
        ])->assertOk();

        $erasureRequest->refresh();
        $this->assertSame(DataErasureRequestStatus::Completed, $erasureRequest->status);
        $this->assertNotNull($erasureRequest->processed_at);

        $trashedUser = User::withTrashed()->find($consumer->id);
        $this->assertNotNull($trashedUser?->deleted_at);
        $this->assertStringContainsString('@anonymized.wenando.local', (string) $trashedUser?->email);
    }

    public function test_admin_approve_pending_request_records_audit_and_dispatches_job(): void
    {
        Queue::fake();

        $consumer = User::factory()->create(['user_type' => UserType::Consumer]);
        $erasureRequest = DataErasureRequest::query()->create([
            'user_id' => $consumer->id,
            'status' => DataErasureRequestStatus::Pending,
            'requested_at' => now(),
        ]);

        $this->patchJson("/api/v1/admin/privacy/erasure-requests/{$erasureRequest->id}", [
            'action' => 'approve',
            'notes' => 'Identità verificata.',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.erasure_request.status', DataErasureRequestStatus::Pending->value)
            ->assertJsonPath('data.erasure_request.metadata.admin_notes', 'Identità verificata.');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => AuditAction::PrivacyErasureApproved->value,
            'subject_type' => DataErasureRequest::class,
            'subject_id' => $erasureRequest->id,
        ]);

        Queue::assertPushed(ProcessDataErasureRequest::class, function (ProcessDataErasureRequest $job) use ($erasureRequest): bool {
            return $job->erasureRequestId === $erasureRequest->id;
        });
    }

    public function test_admin_reject_pending_request_marks_rejected(): void
    {
        $consumer = User::factory()->create(['user_type' => UserType::Consumer]);
        $erasureRequest = DataErasureRequest::query()->create([
            'user_id' => $consumer->id,
            'status' => DataErasureRequestStatus::Pending,
            'requested_at' => now(),
        ]);

        $this->patchJson("/api/v1/admin/privacy/erasure-requests/{$erasureRequest->id}", [
            'action' => 'reject',
            'notes' => 'Identità non verificata.',
        ])
            ->assertOk()
            ->assertJsonPath('data.erasure_request.status', DataErasureRequestStatus::Rejected->value);

        $this->assertDatabaseHas('data_erasure_requests', [
            'id' => $erasureRequest->id,
            'status' => DataErasureRequestStatus::Rejected->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => AuditAction::PrivacyErasureRejected->value,
        ]);
    }

    public function test_admin_cannot_reject_processing_request(): void
    {
        $consumer = User::factory()->create(['user_type' => UserType::Consumer]);
        $erasureRequest = DataErasureRequest::query()->create([
            'user_id' => $consumer->id,
            'status' => DataErasureRequestStatus::Processing,
            'requested_at' => now(),
        ]);

        $this->patchJson("/api/v1/admin/privacy/erasure-requests/{$erasureRequest->id}", [
            'action' => 'reject',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ERASURE_NOT_REJECTABLE');
    }

    public function test_admin_mark_reviewed_updates_metadata_without_status_change(): void
    {
        $consumer = User::factory()->create(['user_type' => UserType::Consumer]);
        $erasureRequest = DataErasureRequest::query()->create([
            'user_id' => $consumer->id,
            'status' => DataErasureRequestStatus::Processing,
            'requested_at' => now(),
        ]);

        $this->patchJson("/api/v1/admin/privacy/erasure-requests/{$erasureRequest->id}", [
            'action' => 'review',
            'notes' => 'Presa in carico.',
        ])
            ->assertOk()
            ->assertJsonPath('data.erasure_request.status', DataErasureRequestStatus::Processing->value)
            ->assertJsonPath('data.erasure_request.metadata.admin_notes', 'Presa in carico.');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => AuditAction::PrivacyErasureReviewed->value,
        ]);
    }

    public function test_patch_validates_action(): void
    {
        $consumer = User::factory()->create(['user_type' => UserType::Consumer]);
        $erasureRequest = DataErasureRequest::query()->create([
            'user_id' => $consumer->id,
            'status' => DataErasureRequestStatus::Pending,
            'requested_at' => now(),
        ]);

        $this->patchJson("/api/v1/admin/privacy/erasure-requests/{$erasureRequest->id}", [
            'action' => 'invalid',
        ])->assertUnprocessable();
    }

    public function test_erasure_requests_surface_in_admin_notifications(): void
    {
        $consumer = User::factory()->create([
            'user_type' => UserType::Consumer,
            'email' => 'dsar@example.com',
        ]);

        DataErasureRequest::query()->create([
            'user_id' => $consumer->id,
            'status' => DataErasureRequestStatus::Pending,
            'requested_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/notifications')
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Richiesta cancellazione dati',
                'message' => 'dsar@example.com — in attesa di revisione',
            ]);
    }
}
