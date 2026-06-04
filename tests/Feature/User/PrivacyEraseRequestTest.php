<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Enums\AuditAction;
use App\Enums\DataErasureRequestStatus;
use App\Enums\UserType;
use App\Jobs\ProcessDataErasureRequest;
use App\Mail\PrivacyErasureRequestMail;
use App\Models\DataErasureRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivacyEraseRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_consumer_can_submit_erase_request(): void
    {
        Queue::fake();
        Mail::fake();

        config(['wenando.privacy_contact_email' => 'hola@wenando.com']);

        $user = User::factory()->create([
            'user_type' => UserType::Consumer,
            'email' => 'erase@example.com',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/user/privacy/erase-request', [
            'confirmed' => true,
            'reason' => 'Non uso più il servizio.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.erasure_request.status', DataErasureRequestStatus::Pending->value)
            ->assertJsonStructure(['data' => ['erasure_request' => ['id', 'requested_at', 'message']]]);

        $this->assertDatabaseHas('data_erasure_requests', [
            'user_id' => $user->id,
            'status' => DataErasureRequestStatus::Pending->value,
            'reason' => 'Non uso più il servizio.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::PrivacyEraseRequest->value,
        ]);

        Queue::assertNotPushed(ProcessDataErasureRequest::class);

        $this->assertNull(User::withTrashed()->find($user->id)?->deleted_at);

        Mail::assertQueued(PrivacyErasureRequestMail::class, function (PrivacyErasureRequestMail $mail) use ($user): bool {
            return $mail->user->is($user);
        });
    }

    public function test_erase_request_requires_authentication(): void
    {
        $this->postJson('/api/v1/user/privacy/erase-request', [
            'confirmed' => true,
        ])->assertUnauthorized();
    }

    public function test_erase_request_requires_confirmation(): void
    {
        $user = User::factory()->create(['user_type' => UserType::Consumer]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/privacy/erase-request', [
            'confirmed' => false,
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertDatabaseCount('data_erasure_requests', 0);
    }

    public function test_duplicate_pending_erase_request_is_rejected(): void
    {
        Queue::fake();

        $user = User::factory()->create(['user_type' => UserType::Consumer]);
        Sanctum::actingAs($user);

        DataErasureRequest::query()->create([
            'user_id' => $user->id,
            'status' => DataErasureRequestStatus::Pending,
            'requested_at' => now(),
        ]);

        $this->postJson('/api/v1/user/privacy/erase-request', [
            'confirmed' => true,
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'ERASURE_REQUEST_PENDING');

        $this->assertDatabaseCount('data_erasure_requests', 1);
        Queue::assertNothingPushed();
    }
}
