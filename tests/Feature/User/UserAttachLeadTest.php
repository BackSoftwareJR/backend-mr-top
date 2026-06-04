<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Enums\LeadStatus;
use App\Enums\UserType;
use App\Models\Lead;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAttachLeadTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    private User $consumer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->consumer = User::factory()->create([
            'user_type' => UserType::Consumer,
            'name' => 'consumer',
            'email' => 'consumer@example.com',
            'phone' => null,
        ]);
    }

    private function actingAsConsumer(): void
    {
        Sanctum::actingAs($this->consumer);
    }

    public function test_consumer_can_attach_orphan_lead_and_hydrate_profile(): void
    {
        $this->actingAsConsumer();
        $lead = $this->createOrphanLead([
            'contact_name' => 'Giulia Bianchi',
            'contact_phone' => '+39 340 999 8888',
        ]);

        $response = $this->postJson('/api/v1/user/leads', [
            'lead_uuid' => $lead->uuid,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.lead.uuid', $lead->uuid)
            ->assertJsonPath('data.user.name', 'Giulia Bianchi')
            ->assertJsonPath('data.user.phone', '+39 340 999 8888');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'user_id' => $this->consumer->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->consumer->id,
            'name' => 'Giulia Bianchi',
            'phone' => '+39 340 999 8888',
        ]);
    }

    public function test_attach_is_idempotent_for_same_user(): void
    {
        $this->actingAsConsumer();
        $lead = $this->createOrphanLead();
        $lead->update(['user_id' => $this->consumer->id]);

        $this->postJson('/api/v1/user/leads', [
            'lead_uuid' => $lead->uuid,
        ])->assertOk()
            ->assertJsonPath('data.lead.uuid', $lead->uuid);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'user_id' => $this->consumer->id,
        ]);
    }

    public function test_attach_rejects_lead_owned_by_another_user(): void
    {
        $this->actingAsConsumer();
        $other = User::factory()->create(['user_type' => UserType::Consumer]);
        $lead = $this->createOrphanLead(['user_id' => $other->id]);

        $this->postJson('/api/v1/user/leads', [
            'lead_uuid' => $lead->uuid,
        ])->assertForbidden();
    }

    public function test_attach_requires_valid_uuid(): void
    {
        $this->actingAsConsumer();
        $this->postJson('/api/v1/user/leads', [
            'lead_uuid' => 'not-a-uuid',
        ])->assertStatus(422);
    }

    public function test_attach_requires_authentication(): void
    {
        $lead = $this->createOrphanLead();

        $this->postJson('/api/v1/user/leads', [
            'lead_uuid' => $lead->uuid,
        ])->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOrphanLead(array $overrides = []): Lead
    {
        return Lead::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-TEST-'.Str::random(4),
            'sector_id' => $this->sector->id,
            'user_id' => null,
            'status' => LeadStatus::Processing,
            'payload' => [
                'autonomy' => 'parziale',
                'location' => ['label' => 'Milano (MI)', 'value' => 'milano-mi'],
                'budget' => ['min' => 1500, 'max' => 2500],
                'contact' => ['nome' => 'Giulia', 'telefono' => '+39 340 999 8888'],
            ],
            'contact_name' => 'Giulia Bianchi',
            'contact_phone' => '+39 340 999 8888',
            'location_label' => 'Milano (MI)',
            'budget_min' => 1500,
            'budget_max' => 2500,
            'need_summary' => 'Assistenza per autonomia parziale',
            'title' => 'Senior Care · Assistenza per autonomia parziale',
        ], $overrides));
    }
}
