<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\VettingStatus;
use App\Mail\B2bWelcomeMail;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegisterOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVACY_HASH = 'ba8597379922b6fe4154e6448474495391e40e7fa2cd8688f51cf159f7252006';

    private const TERMS_B2B_HASH = '3660d5b1e1b49d528e87bf7d844abd9eee72dec18dd798bee1d097bd3d1ce008';

    protected function setUp(): void
    {
        parent::setUp();

        Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);
    }

    public function test_b2b_register_queues_welcome_mail(): void
    {
        Mail::fake();
        config(['app.frontend_url' => 'https://app.wenando.test']);

        $this->postJson('/api/v1/b2b/register', [
            'email' => 'welcome@struttura.it',
            'organization_name' => 'Casa Welcome',
            'legal_name' => 'Casa Welcome S.r.l.',
            'privacy_policy_accepted' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
        ])->assertCreated();

        Mail::assertQueued(B2bWelcomeMail::class, function (B2bWelcomeMail $mail): bool {
            return $mail->hasTo('welcome@struttura.it')
                && $mail->recipientName === 'Casa Welcome';
        });
    }

    public function test_b2b_register_and_onboarding_flow(): void
    {
        $register = $this->postJson('/api/v1/b2b/register', [
            'email' => 'newpartner@struttura.it',
            'organization_name' => 'Nuova Casa',
            'legal_name' => 'Nuova Casa S.r.l.',
            'privacy_policy_accepted' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
        ]);

        $register->assertCreated()
            ->assertJsonPath('data.company.organization_name', 'Nuova Casa');

        $companyId = User::query()->where('email', 'newpartner@struttura.it')->firstOrFail()
            ->companies()->firstOrFail()->id;

        $this->assertDatabaseHas('company_profiles', [
            'company_id' => $companyId,
            'display_name' => 'Nuova Casa',
            'service_type' => 'Assistenza Senior Care',
        ]);

        $token = $register->json('data.token');
        $user = User::query()->where('email', 'newpartner@struttura.it')->first();
        Sanctum::actingAs($user, ['*']);

        $this->patchJson('/api/v1/b2b/onboarding', [
            'vat' => 'IT12345678901',
            'sdi' => 'ABCDEFG',
        ])->assertOk();

        $visura = UploadedFile::fake()->create('visura-camerale.pdf', 128, 'application/pdf');
        $this->post('/api/v1/b2b/onboarding/documents', [
            'type' => 'visura',
            'file' => $visura,
        ])
            ->assertOk()
            ->assertJsonPath('data.type', 'visura')
            ->assertJsonPath('data.file_name', 'visura-camerale.pdf');

        $this->assertDatabaseHas('company_documents', [
            'company_id' => $user->companies()->first()?->id,
            'original_name' => 'visura-camerale.pdf',
        ]);

        $identity = UploadedFile::fake()->create('documento-identita.pdf', 64, 'application/pdf');
        $this->post('/api/v1/b2b/onboarding/documents', [
            'type' => 'identity',
            'file' => $identity,
        ])
            ->assertOk()
            ->assertJsonPath('data.type', 'identity')
            ->assertJsonPath('data.file_name', 'documento-identita.pdf');

        $this->getJson('/api/v1/b2b/onboarding')
            ->assertOk()
            ->assertJsonPath('data.data.visura', 'visura-camerale.pdf')
            ->assertJsonPath('data.data.identity_doc', 'documento-identita.pdf');

        $this->putJson('/api/v1/b2b/coverage-zone', [
            'center_lat' => 41.9028,
            'center_lng' => 12.4964,
            'radius_km' => 20,
            'label' => 'Roma',
        ])->assertOk();

        $this->postJson('/api/v1/b2b/onboarding/submit', [
            'terms_b2b_accepted' => true,
            'terms_text_hash' => self::TERMS_B2B_HASH,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', VettingStatus::PendingReview->value);

        $this->getJson('/api/v1/b2b/onboarding/status')
            ->assertOk()
            ->assertJsonPath('data.status', VettingStatus::PendingReview->value)
            ->assertJsonPath('data.onboarding_complete', false)
            ->assertJsonPath('data.redirect_to', '/pro/onboarding');

        $this->getJson('/api/v1/b2b/wallet')
            ->assertOk()
            ->assertJsonPath('data.balance_credits', 0);
    }

    public function test_onboarding_status_redirect_by_vetting_state(): void
    {
        $register = $this->postJson('/api/v1/b2b/register', [
            'email' => 'status@struttura.it',
            'organization_name' => 'Status Test',
            'legal_name' => 'Status Test S.r.l.',
            'privacy_policy_accepted' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
        ]);

        $user = User::query()->where('email', 'status@struttura.it')->firstOrFail();
        $company = $user->companies()->firstOrFail();
        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/b2b/onboarding/status')
            ->assertOk()
            ->assertJsonPath('data.status', VettingStatus::InProgress->value)
            ->assertJsonPath('data.onboarding_complete', false)
            ->assertJsonPath('data.redirect_to', '/pro/onboarding');

        $company->update(['vetting_status' => VettingStatus::PendingReview]);
        $this->getJson('/api/v1/b2b/onboarding/status')
            ->assertJsonPath('data.status', VettingStatus::PendingReview->value)
            ->assertJsonPath('data.redirect_to', '/pro/onboarding');

        $company->update(['vetting_status' => VettingStatus::Approved]);
        $this->getJson('/api/v1/b2b/onboarding/status')
            ->assertJsonPath('data.status', VettingStatus::Approved->value)
            ->assertJsonPath('data.onboarding_complete', true)
            ->assertJsonPath('data.redirect_to', '/pro/dashboard');

        $company->update([
            'vetting_status' => VettingStatus::Rejected,
            'rejection_reason' => 'Documentazione incompleta',
        ]);
        $this->getJson('/api/v1/b2b/onboarding/status')
            ->assertJsonPath('data.status', VettingStatus::Rejected->value)
            ->assertJsonPath('data.onboarding_complete', false)
            ->assertJsonPath('data.redirect_to', '/pro/onboarding')
            ->assertJsonPath('data.rejection_reason', 'Documentazione incompleta');

        $company->update(['vetting_status' => VettingStatus::Suspended]);
        $this->getJson('/api/v1/b2b/onboarding/status')
            ->assertJsonPath('data.status', VettingStatus::Suspended->value)
            ->assertJsonPath('data.onboarding_complete', false)
            ->assertJsonPath('data.redirect_to', '/pro/onboarding');
    }

    public function test_wallet_recharge_mock_success(): void
    {
        config(['wenando.wallet_instant_recharge' => true]);

        $register = $this->postJson('/api/v1/b2b/register', [
            'email' => 'wallet@struttura.it',
            'organization_name' => 'Wallet Test',
            'legal_name' => 'Wallet Test S.r.l.',
            'privacy_policy_accepted' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
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
