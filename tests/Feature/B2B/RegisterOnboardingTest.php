<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\VettingStatus;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegisterOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);
    }

    public function test_b2b_register_and_onboarding_flow(): void
    {
        $register = $this->postJson('/api/v1/b2b/register', [
            'email' => 'newpartner@struttura.it',
            'organization_name' => 'Nuova Casa',
            'legal_name' => 'Nuova Casa S.r.l.',
        ]);

        $register->assertCreated()
            ->assertJsonPath('data.company.organization_name', 'Nuova Casa');

        $token = $register->json('data.token');
        $user = User::query()->where('email', 'newpartner@struttura.it')->first();
        Sanctum::actingAs($user, ['*']);

        $this->patchJson('/api/v1/b2b/onboarding', [
            'vat' => 'IT12345678901',
            'sdi' => 'ABCDEFG',
        ])->assertOk();

        $this->postJson('/api/v1/b2b/onboarding/submit')
            ->assertOk()
            ->assertJsonPath('data.status', VettingStatus::PendingReview->value);

        $this->getJson('/api/v1/b2b/wallet')
            ->assertOk()
            ->assertJsonPath('data.balance_credits', 0);
    }

    public function test_wallet_recharge_mock_success(): void
    {
        $register = $this->postJson('/api/v1/b2b/register', [
            'email' => 'wallet@struttura.it',
            'organization_name' => 'Wallet Test',
            'legal_name' => 'Wallet Test S.r.l.',
        ]);

        $user = User::query()->where('email', 'wallet@struttura.it')->first();
        $user->companies()->first()?->update(['vetting_status' => VettingStatus::Approved]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/b2b/wallet/recharge', [
            'amount' => 100,
            'payment_method' => 'card',
        ])->assertOk()
            ->assertJsonPath('data.wallet.balance_credits', 100);

        $this->assertDatabaseHas('transactions', [
            'type' => 'recharge',
            'credits_delta' => 100,
        ]);
    }
}
