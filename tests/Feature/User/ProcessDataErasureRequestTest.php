<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Enums\DataErasureRequestStatus;
use App\Enums\LeadStatus;
use App\Enums\UserType;
use App\Jobs\ProcessDataErasureRequest;
use App\Models\DataErasureRequest;
use App\Models\Lead;
use App\Models\Sector;
use App\Models\User;
use App\Services\LeadAnonymizationService;
use App\Services\PrivacyErasureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessDataErasureRequestTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);
    }

    public function test_job_soft_deletes_user_anonymizes_leads_and_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'user_type' => UserType::Consumer,
            'email' => 'erase-job@example.com',
            'name' => 'Da Cancellare',
            'phone' => '+39 333 999 8888',
        ]);

        $token = $user->createToken('test-device');

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-ERASE-1',
            'sector_id' => $this->sector->id,
            'user_id' => $user->id,
            'status' => LeadStatus::Routed,
            'contact_name' => 'Da Cancellare',
            'contact_email' => 'erase-job@example.com',
            'contact_phone' => '+39 333 999 8888',
            'location_label' => 'Milano (MI)',
            'need_summary' => 'Assistenza residenziale',
            'payload' => ['contact' => ['email' => 'erase-job@example.com']],
        ]);

        $erasureRequest = DataErasureRequest::query()->create([
            'user_id' => $user->id,
            'status' => DataErasureRequestStatus::Pending,
            'requested_at' => now(),
        ]);

        $job = new ProcessDataErasureRequest($erasureRequest->id);
        $job->handle(app(PrivacyErasureService::class));

        $erasureRequest->refresh();
        $this->assertSame(DataErasureRequestStatus::Completed, $erasureRequest->status);
        $this->assertNotNull($erasureRequest->processed_at);
        $this->assertSame(1, $erasureRequest->metadata['leads_anonymized'] ?? null);

        $trashedUser = User::withTrashed()->find($user->id);
        $this->assertNotNull($trashedUser?->deleted_at);
        $this->assertStringContainsString('@anonymized.wenando.local', (string) $trashedUser?->email);
        $this->assertSame('', $trashedUser?->name);
        $this->assertNull($trashedUser?->phone);

        $lead->refresh();
        $this->assertNull($lead->user_id);
        $this->assertTrue(app(LeadAnonymizationService::class)->isAnonymized($lead));
        $this->assertSame('Richiesta anonimizzata', $lead->need_summary);
        $this->assertSame('Ricerca anonimizzata', $lead->title);
        $this->assertArrayNotHasKey('contact', $lead->payload ?? []);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_job_skips_non_pending_requests(): void
    {
        $user = User::factory()->create(['user_type' => UserType::Consumer]);

        $erasureRequest = DataErasureRequest::query()->create([
            'user_id' => $user->id,
            'status' => DataErasureRequestStatus::Completed,
            'requested_at' => now(),
            'processed_at' => now(),
        ]);

        $job = new ProcessDataErasureRequest($erasureRequest->id);
        $job->handle(app(PrivacyErasureService::class));

        $this->assertNull(User::withTrashed()->find($user->id)?->deleted_at);
    }
}
