<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Company;
use App\Models\Sector;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoogleCalendarOAuthTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Calendar Co',
            'legal_name' => 'Calendar Co S.r.l.',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'dynamic_attributes' => [],
        ]);

        Wallet::query()->create([
            'company_id' => $this->company->id,
            'balance_credits' => 50,
            'total_spent_credits' => 0,
            'currency' => 'EUR',
        ]);

        $this->partner = User::factory()->create([
            'user_type' => UserType::B2b,
            'email' => 'calendar@partner.it',
            'password' => Hash::make('password123'),
        ]);

        $this->company->users()->attach($this->partner->id, ['role' => 'owner']);

        config([
            'services.google.client_id' => 'fake-google-client-id.apps.googleusercontent.com',
            'services.google.client_secret' => 'fake-google-client-secret',
            'services.google.redirect_uri' => 'https://api.wenando.test/api/v1/b2b/integrations/google/callback',
        ]);

        Sanctum::actingAs($this->partner);
    }

    public function test_partner_gets_google_oauth_authorization_url(): void
    {
        $response = $this->getJson('/api/v1/b2b/integrations/google/connect')
            ->assertOk();

        $url = $response->json('data.authorization_url');
        $this->assertIsString($url);
        $this->assertStringContainsString('accounts.google.com/o/oauth2/v2/auth', $url);
        $this->assertStringContainsString('fake-google-client-id', $url);
        $this->assertStringContainsString('calendar.events', $url);

        $state = $response->json('data.state');
        $this->assertIsString($state);
        $this->assertNotSame('', $state);
    }

    public function test_google_status_returns_not_connected_by_default(): void
    {
        $this->getJson('/api/v1/b2b/integrations/google/status')
            ->assertOk()
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.sync_enabled', false);
    }

    public function test_google_connect_returns_503_when_not_configured(): void
    {
        config([
            'services.google.client_id' => '',
            'services.google.redirect_uri' => '',
        ]);

        $this->getJson('/api/v1/b2b/integrations/google/connect')
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'GOOGLE_NOT_CONFIGURED');
    }
}
