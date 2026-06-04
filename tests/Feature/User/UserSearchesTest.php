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

class UserSearchesTest extends TestCase
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
        ]);

        Sanctum::actingAs($this->consumer);
    }

    public function test_user_searches_list_returns_lead_title(): void
    {
        $lead = $this->createLeadForUser([
            'title' => 'Senior Care · Assistenza per autonomia parziale',
            'need_summary' => 'Assistenza per autonomia parziale',
            'status' => LeadStatus::Routed,
        ]);

        $response = $this->getJson('/api/v1/user/searches');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.searches.0.id', $lead->id)
            ->assertJsonPath(
                'data.searches.0.title',
                'Senior Care · Assistenza per autonomia parziale',
            )
            ->assertJsonPath('data.searches.0.location', 'Milano (MI)')
            ->assertJsonPath('data.searches.0.status', 'completed');
    }

    public function test_user_home_latest_search_includes_title(): void
    {
        $this->createLeadForUser([
            'title' => 'Senior Care · Assistenza per persona autosufficiente',
            'need_summary' => 'Assistenza per persona autosufficiente',
            'status' => LeadStatus::Processing,
        ]);

        $response = $this->getJson('/api/v1/user/home');

        $response->assertOk()
            ->assertJsonPath(
                'data.latest_search.title',
                'Senior Care · Assistenza per persona autosufficiente',
            )
            ->assertJsonPath('data.latest_search.status', 'processing');
    }

    public function test_user_search_detail_returns_title(): void
    {
        $lead = $this->createLeadForUser([
            'title' => 'Senior Care · Assistenza per persona non autosufficiente',
            'need_summary' => 'Assistenza per persona non autosufficiente',
            'status' => LeadStatus::Routed,
        ]);

        $response = $this->getJson('/api/v1/user/searches/'.$lead->uuid);

        $response->assertOk()
            ->assertJsonPath(
                'data.search.title',
                'Senior Care · Assistenza per persona non autosufficiente',
            );
    }

    public function test_user_search_detail_resolves_by_public_ref(): void
    {
        $lead = $this->createLeadForUser([
            'public_ref' => 'LD-DETAIL-01',
            'title' => 'Ricerca via public ref',
            'status' => LeadStatus::Routed,
        ]);

        $this->getJson('/api/v1/user/searches/'.$lead->public_ref)
            ->assertOk()
            ->assertJsonPath('data.search.title', 'Ricerca via public ref');
    }

    public function test_user_search_detail_forbidden_for_other_users_lead(): void
    {
        $otherUser = User::factory()->create(['user_type' => UserType::Consumer]);
        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-OTHER-DETAIL',
            'sector_id' => $this->sector->id,
            'user_id' => $otherUser->id,
            'status' => LeadStatus::Routed,
            'payload' => [],
            'location_label' => 'Roma (RM)',
            'title' => 'Lead altrui',
        ]);

        $this->getJson('/api/v1/user/searches/'.$lead->uuid)->assertForbidden();
    }

    public function test_user_search_title_falls_back_to_need_summary_when_title_null(): void
    {
        $lead = $this->createLeadForUser([
            'title' => null,
            'need_summary' => 'Assistenza domiciliare h24',
            'status' => LeadStatus::Routed,
        ]);

        $response = $this->getJson('/api/v1/user/searches/'.$lead->uuid);

        $response->assertOk()
            ->assertJsonPath('data.search.title', 'Assistenza domiciliare h24');
    }

    public function test_user_searches_list_supports_pagination(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $lead = $this->createLeadForUser([
                'public_ref' => 'LD-PAGE-'.$i,
                'title' => 'Ricerca pagina '.$i,
            ]);
            $lead->forceFill([
                'created_at' => now()->subDays(4 - $i),
                'updated_at' => now()->subDays(4 - $i),
            ])->saveQuietly();
        }

        $pageOne = $this->getJson('/api/v1/user/searches?page=1&per_page=2');
        $pageOne->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data.searches')
            ->assertJsonPath('data.searches.0.title', 'Ricerca pagina 3')
            ->assertJsonPath('data.searches.1.title', 'Ricerca pagina 2');

        $pageTwo = $this->getJson('/api/v1/user/searches?page=2&per_page=2');
        $pageTwo->assertOk()
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(1, 'data.searches')
            ->assertJsonPath('data.searches.0.title', 'Ricerca pagina 1');
    }

    public function test_user_searches_list_defaults_to_page_one(): void
    {
        $this->createLeadForUser(['title' => 'Unica ricerca']);

        $this->getJson('/api/v1/user/searches')
            ->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.last_page', 1);
    }

    public function test_consumer_can_update_search_title_by_uuid(): void
    {
        $lead = $this->createLeadForUser([
            'title' => 'Titolo originale',
            'status' => LeadStatus::Routed,
        ]);

        $response = $this->patchJson('/api/v1/user/searches/'.$lead->uuid, [
            'title' => 'Ricerca per la nonna',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.search.title', 'Ricerca per la nonna')
            ->assertJsonPath('data.search.id', $lead->id);

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'title' => 'Ricerca per la nonna',
        ]);
    }

    public function test_consumer_can_update_search_title_by_public_ref(): void
    {
        $lead = $this->createLeadForUser([
            'public_ref' => 'LD-RENAME-01',
            'title' => 'Vecchio titolo',
            'status' => LeadStatus::Routed,
        ]);

        $this->patchJson('/api/v1/user/searches/'.$lead->public_ref, [
            'title' => 'Nuovo titolo personalizzato',
        ])
            ->assertOk()
            ->assertJsonPath('data.search.title', 'Nuovo titolo personalizzato');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'title' => 'Nuovo titolo personalizzato',
        ]);
    }

    public function test_update_search_title_requires_consumer_auth(): void
    {
        $this->app['auth']->forgetGuards();

        $lead = $this->createLeadForUser();

        $this->patchJson('/api/v1/user/searches/'.$lead->uuid, [
            'title' => 'Senza auth',
        ])->assertUnauthorized();
    }

    public function test_update_search_title_forbidden_for_other_users_lead(): void
    {
        $otherUser = User::factory()->create(['user_type' => UserType::Consumer]);
        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-OTHER-01',
            'sector_id' => $this->sector->id,
            'user_id' => $otherUser->id,
            'status' => LeadStatus::Routed,
            'payload' => [],
            'location_label' => 'Roma (RM)',
            'title' => 'Lead altrui',
        ]);

        $this->patchJson('/api/v1/user/searches/'.$lead->uuid, [
            'title' => 'Tentativo di rinomina',
        ])->assertForbidden();
    }

    public function test_update_search_title_validates_required_and_max_length(): void
    {
        $lead = $this->createLeadForUser();

        $this->patchJson('/api/v1/user/searches/'.$lead->uuid, [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['title']]]);

        $this->patchJson('/api/v1/user/searches/'.$lead->uuid, [
            'title' => str_repeat('a', 256),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createLeadForUser(array $overrides = []): Lead
    {
        return Lead::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-TEST-'.Str::random(4),
            'sector_id' => $this->sector->id,
            'user_id' => $this->consumer->id,
            'status' => LeadStatus::Routed,
            'payload' => [
                'autonomy' => 'parziale',
                'location' => ['label' => 'Milano (MI)', 'value' => 'milano-mi'],
                'budget' => ['min' => 1500, 'max' => 2500],
                'contact' => ['nome' => 'Mario', 'telefono' => '+39 333 123 4567'],
            ],
            'contact_name' => 'Mario',
            'contact_phone' => '+39 333 123 4567',
            'location_label' => 'Milano (MI)',
            'budget_min' => 1500,
            'budget_max' => 2500,
            'need_summary' => 'Assistenza per autonomia parziale',
            'title' => 'Senior Care · Assistenza per autonomia parziale',
        ], $overrides));
    }
}
