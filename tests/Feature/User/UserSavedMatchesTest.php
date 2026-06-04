<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Enums\LeadStatus;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadMatch;
use App\Models\SavedMatch;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserSavedMatchesTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    private User $consumer;

    private Lead $lead;

    private LeadMatch $visibleMatch;

    private Company $otherCompany;

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

        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $this->sector->id,
            'organization_name' => 'Casa Serenità',
            'legal_name' => 'Casa Serenità S.r.l.',
            'city' => 'Milano',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
        ]);

        $this->otherCompany = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $this->sector->id,
            'organization_name' => 'Struttura Esterna',
            'legal_name' => 'Struttura Esterna S.r.l.',
            'city' => 'Roma',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
        ]);

        $this->lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-SAVED-01',
            'sector_id' => $this->sector->id,
            'user_id' => $this->consumer->id,
            'status' => LeadStatus::Routed,
            'payload' => ['autonomy' => 'parziale'],
            'location_label' => 'Milano (MI)',
            'title' => 'Ricerca test',
        ]);

        $this->visibleMatch = LeadMatch::query()->create([
            'lead_id' => $this->lead->id,
            'company_id' => $company->id,
            'match_score' => 88,
            'rank' => 1,
            'is_visible_to_consumer' => true,
            'is_in_marketplace' => true,
            'unlock_cost_credits' => 15,
        ]);

        Sanctum::actingAs($this->consumer);
    }

    public function test_consumer_can_save_visible_match_by_lead_match_id(): void
    {
        $response = $this->postJson('/api/v1/user/saved-matches', [
            'lead_match_id' => $this->visibleMatch->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.saved', true);

        $this->assertDatabaseHas('saved_matches', [
            'user_id' => $this->consumer->id,
            'lead_match_id' => $this->visibleMatch->id,
            'company_id' => $this->visibleMatch->company_id,
        ]);
    }

    public function test_consumer_can_save_visible_match_by_company_id(): void
    {
        $this->postJson('/api/v1/user/saved-matches', [
            'company_id' => $this->visibleMatch->company_id,
        ])
            ->assertOk()
            ->assertJsonPath('data.saved', true);

        $this->assertDatabaseHas('saved_matches', [
            'user_id' => $this->consumer->id,
            'company_id' => $this->visibleMatch->company_id,
        ]);
    }

    public function test_consumer_can_unsave_existing_match_without_revalidation(): void
    {
        SavedMatch::query()->create([
            'user_id' => $this->consumer->id,
            'company_id' => $this->otherCompany->id,
            'lead_match_id' => null,
        ]);

        $this->postJson('/api/v1/user/saved-matches', [
            'company_id' => $this->otherCompany->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.saved', false);

        $this->assertSoftDeleted('saved_matches', [
            'user_id' => $this->consumer->id,
            'company_id' => $this->otherCompany->id,
        ]);
    }

    public function test_cannot_save_company_not_in_consumer_results(): void
    {
        $this->postJson('/api/v1/user/saved-matches', [
            'company_id' => $this->otherCompany->id,
        ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'HTTP_ERROR');

        $this->assertDatabaseMissing('saved_matches', [
            'user_id' => $this->consumer->id,
            'company_id' => $this->otherCompany->id,
        ]);
    }

    public function test_cannot_save_match_from_other_users_lead(): void
    {
        $otherUser = User::factory()->create(['user_type' => UserType::Consumer]);
        $foreignLead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-FOREIGN',
            'sector_id' => $this->sector->id,
            'user_id' => $otherUser->id,
            'status' => LeadStatus::Routed,
            'payload' => [],
            'location_label' => 'Roma (RM)',
        ]);

        $foreignMatch = LeadMatch::query()->create([
            'lead_id' => $foreignLead->id,
            'company_id' => $this->otherCompany->id,
            'match_score' => 90,
            'rank' => 1,
            'is_visible_to_consumer' => true,
            'is_in_marketplace' => true,
            'unlock_cost_credits' => 15,
        ]);

        $this->postJson('/api/v1/user/saved-matches', [
            'lead_match_id' => $foreignMatch->id,
        ])->assertForbidden();
    }

    public function test_cannot_save_match_hidden_from_consumer(): void
    {
        $hiddenMatch = LeadMatch::query()->create([
            'lead_id' => $this->lead->id,
            'company_id' => $this->otherCompany->id,
            'match_score' => 40,
            'rank' => 2,
            'is_visible_to_consumer' => false,
            'is_in_marketplace' => false,
            'unlock_cost_credits' => 15,
        ]);

        $this->postJson('/api/v1/user/saved-matches', [
            'lead_match_id' => $hiddenMatch->id,
        ])->assertForbidden();
    }

    public function test_toggle_saved_match_requires_one_identifier(): void
    {
        $this->postJson('/api/v1/user/saved-matches', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_toggle_saved_match_rejects_both_identifiers(): void
    {
        $this->postJson('/api/v1/user/saved-matches', [
            'company_id' => $this->visibleMatch->company_id,
            'lead_match_id' => $this->visibleMatch->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_saved_matches_list_returns_company_ids(): void
    {
        SavedMatch::query()->create([
            'user_id' => $this->consumer->id,
            'company_id' => $this->visibleMatch->company_id,
            'lead_match_id' => $this->visibleMatch->id,
        ]);

        $this->getJson('/api/v1/user/saved-matches')
            ->assertOk()
            ->assertJsonPath('data.ids.0', $this->visibleMatch->company_id);
    }
}
