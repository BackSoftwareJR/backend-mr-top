<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AppointmentType;
use App\Enums\AuditAction;
use App\Enums\CompanyTier;
use App\Enums\LeadStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserType;
use App\Enums\VettingStatus;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\ImpersonationSession;
use App\Models\Lead;
use App\Models\PaymentIntent;
use App\Models\Sector;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'user_type' => UserType::Superadmin,
            'email' => 'admin@wenando.com',
        ]);

        Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Pending Co',
            'legal_name' => 'Pending Co S.r.l.',
            'vetting_status' => VettingStatus::PendingReview,
        ]);

        Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'status' => 'processing',
            'payload' => ['autonomy' => 'parziale'],
            'contact_name' => 'Test User',
            'location_label' => 'Milano',
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_admin_lists_partners_and_leads(): void
    {
        $this->getJson('/api/v1/admin/partners')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['partners']]);

        $this->getJson('/api/v1/admin/leads')
            ->assertOk()
            ->assertJsonStructure(['data' => ['leads']]);
    }

    public function test_admin_suspend_partner(): void
    {
        $company = Company::query()->first();
        $company->update(['vetting_status' => VettingStatus::Approved]);

        $this->postJson("/api/v1/admin/partners/{$company->uuid}/suspend", [
            'reason' => 'Test suspend',
        ])->assertOk()
            ->assertJsonPath('data.company.vetting_status', VettingStatus::Suspended->value);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => AuditAction::PartnerSuspended->value,
            'subject_type' => Company::class,
            'subject_id' => $company->id,
        ]);

        $audit = AuditLog::query()
            ->forAction(AuditAction::PartnerSuspended)
            ->where('user_id', $this->admin->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('Test suspend', $audit->metadata['reason'] ?? null);
    }

    public function test_admin_assign_lead_with_company_uuid(): void
    {
        $sector = Sector::query()->first();
        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Assign Target Co',
            'legal_name' => 'Assign Target Co S.r.l.',
            'vetting_status' => VettingStatus::Approved,
        ]);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'status' => LeadStatus::Processing,
            'payload' => ['autonomy' => 'parziale'],
            'contact_name' => 'Assign Test User',
            'location_label' => 'Roma',
        ]);

        $this->patchJson("/api/v1/admin/leads/{$lead->id}/assign", [
            'partner_id' => $company->uuid,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['lead', 'assignment']]);

        $lead->refresh();
        $this->assertSame(LeadStatus::Assigned, $lead->status);
        $this->assertSame('Assegnato', $lead->admin_status);
        $this->assertDatabaseHas('lead_matches', [
            'lead_id' => $lead->id,
            'company_id' => $company->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => AuditAction::LeadManualAssign->value,
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
        ]);

        $assignAudit = AuditLog::query()
            ->forAction(AuditAction::LeadManualAssign)
            ->where('user_id', $this->admin->id)
            ->first();

        $this->assertNotNull($assignAudit);
        $this->assertSame($company->id, $assignAudit->metadata['company_id'] ?? null);
    }

    public function test_admin_reroute_lead_writes_audit_log(): void
    {
        $lead = Lead::query()->first();

        $this->postJson("/api/v1/admin/leads/{$lead->id}/reroute")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['job_id']]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => AuditAction::LeadReroute->value,
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
        ]);
    }

    public function test_admin_risk_indicators_returns_domain_signals(): void
    {
        $sector = Sector::query()->first();
        $approvedCompany = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Risk Low Wallet Co',
            'legal_name' => 'Risk Low Wallet Co S.r.l.',
            'vetting_status' => VettingStatus::Approved,
        ]);

        Wallet::query()->create([
            'company_id' => $approvedCompany->id,
            'balance_credits' => 5,
            'total_spent_credits' => 0,
            'currency' => 'EUR',
        ]);

        Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Risk Suspended Co',
            'legal_name' => 'Risk Suspended Co S.r.l.',
            'vetting_status' => VettingStatus::Suspended,
        ]);

        PaymentIntent::query()->create([
            'public_ref' => 'PI-RISK-PENDING-1',
            'company_id' => $approvedCompany->id,
            'amount_cents' => 5000,
            'credits' => 50,
            'status' => PaymentIntentStatus::Pending,
            'payment_method' => PaymentMethod::Transfer,
            'provider' => 'bank_transfer',
            'provider_ref' => 'WEN-RISK-1',
        ]);

        $response = $this->getJson('/api/v1/admin/risk-indicators')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'indicators' => [
                        '*' => ['label', 'value', 'status', 'detail'],
                    ],
                ],
            ]);

        $indicators = collect($response->json('data.indicators'))->keyBy('label');

        $this->assertGreaterThanOrEqual(1, $indicators->get('Partner in attesa')['value']);
        $this->assertSame('warn', $indicators->get('Partner in attesa')['status']);

        $this->assertGreaterThanOrEqual(1, $indicators->get('Lead non assegnati')['value']);
        $this->assertSame('alert', $indicators->get('Lead non assegnati')['status']);

        $this->assertSame(1, $indicators->get('Partner sospesi')['value']);
        $this->assertSame('warn', $indicators->get('Partner sospesi')['status']);

        $this->assertSame(1, $indicators->get('Wallet partner bassi')['value']);
        $this->assertSame('warn', $indicators->get('Wallet partner bassi')['status']);

        $this->assertSame(1, $indicators->get('Bonifici in attesa')['value']);
        $this->assertSame('warn', $indicators->get('Bonifici in attesa')['status']);

        $this->assertArrayHasKey('Lead in elaborazione', $indicators->all());
    }

    public function test_admin_lists_notifications_from_domain_events(): void
    {
        $response = $this->getJson('/api/v1/admin/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'notifications' => [
                        '*' => ['id', 'title', 'message', 'created_at', 'read'],
                    ],
                ],
            ]);

        $notifications = $response->json('data.notifications');
        $this->assertNotEmpty($notifications);

        $messages = collect($notifications)->pluck('message')->implode(' ');
        $this->assertStringContainsString('Pending Co', $messages);
        $this->assertTrue(
            collect($notifications)->contains(
                fn (array $n): bool => $n['title'] === 'Lead ad alta priorità',
            ),
        );
    }

    public function test_admin_impersonate_partner_issues_short_lived_token(): void
    {
        $sector = Sector::query()->first();
        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Impersonate Target Co',
            'legal_name' => 'Impersonate Target Co S.r.l.',
            'vetting_status' => VettingStatus::Approved,
        ]);

        $partnerUser = User::factory()->create([
            'user_type' => UserType::B2b,
            'email' => 'partner@impersonate.test',
            'name' => 'Partner Staff',
        ]);
        $company->users()->attach($partnerUser->id, ['role' => 'owner']);

        $response = $this->postJson("/api/v1/admin/partners/{$company->uuid}/impersonate")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'impersonation_token',
                    'expires_at',
                    'partner' => ['id', 'email', 'organization_name'],
                ],
            ])
            ->assertJsonPath('data.partner.organization_name', 'Impersonate Target Co');

        $token = $response->json('data.impersonation_token');
        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $partnerUser->id,
            'name' => 'impersonation',
        ]);

        $accessToken = PersonalAccessToken::findToken($token);
        $this->assertNotNull($accessToken);
        $this->assertSame($partnerUser->id, $accessToken->tokenable_id);
        $this->assertTrue($accessToken->expires_at->isFuture());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => AuditAction::ImpersonateStart->value,
            'subject_type' => Company::class,
            'subject_id' => $company->id,
        ]);

        $audit = AuditLog::query()
            ->forAction(AuditAction::ImpersonateStart)
            ->where('user_id', $this->admin->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($company->uuid, $audit->metadata['company_uuid'] ?? null);
        $this->assertSame($partnerUser->id, $audit->metadata['partner_user_id'] ?? null);

        $session = ImpersonationSession::query()
            ->where('admin_user_id', $this->admin->id)
            ->where('company_id', $company->id)
            ->first();

        $this->assertNotNull($session);
        $this->assertNull($session->ended_at);
        $this->assertSame($audit->id, $session->start_audit_log_id);
        $this->assertSame($accessToken->id, $session->personal_access_token_id);
    }

    public function test_impersonation_end_audit_on_token_revocation(): void
    {
        $sector = Sector::query()->first();
        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Revoke Target Co',
            'legal_name' => 'Revoke Target Co S.r.l.',
            'vetting_status' => VettingStatus::Approved,
        ]);

        $partnerUser = User::factory()->create([
            'user_type' => UserType::B2b,
            'email' => 'partner@revoke.test',
        ]);
        $company->users()->attach($partnerUser->id, ['role' => 'owner']);

        $this->postJson("/api/v1/admin/partners/{$company->uuid}/impersonate")->assertOk();

        $session = ImpersonationSession::query()
            ->where('company_id', $company->id)
            ->active()
            ->firstOrFail();

        $token = PersonalAccessToken::query()->findOrFail($session->personal_access_token_id);
        $token->delete();

        $session->refresh();
        $this->assertNotNull($session->ended_at);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => AuditAction::ImpersonateEnd->value,
            'subject_type' => Company::class,
            'subject_id' => $company->id,
        ]);

        $endAudit = AuditLog::query()
            ->forAction(AuditAction::ImpersonateEnd)
            ->where('user_id', $this->admin->id)
            ->first();

        $this->assertNotNull($endAudit);
        $this->assertSame($session->id, $endAudit->metadata['impersonation_session_id'] ?? null);
        $this->assertGreaterThanOrEqual(0, $endAudit->metadata['duration_seconds'] ?? -1);
    }

    public function test_impersonation_end_audit_when_session_expires(): void
    {
        $sector = Sector::query()->first();
        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Expire Target Co',
            'legal_name' => 'Expire Target Co S.r.l.',
            'vetting_status' => VettingStatus::Approved,
        ]);

        $partnerUser = User::factory()->create([
            'user_type' => UserType::B2b,
            'email' => 'partner@expire.test',
        ]);
        $company->users()->attach($partnerUser->id, ['role' => 'owner']);

        $this->postJson("/api/v1/admin/partners/{$company->uuid}/impersonate")->assertOk();

        $session = ImpersonationSession::query()
            ->where('company_id', $company->id)
            ->active()
            ->firstOrFail();

        $this->travel(16)->minutes();

        $this->artisan('impersonation:close-expired')->assertSuccessful();

        $session->refresh();
        $this->assertNotNull($session->ended_at);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => AuditAction::ImpersonateEnd->value,
        ]);
    }

    public function test_admin_impersonate_fails_without_partner_user(): void
    {
        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => Sector::query()->first()->id,
            'organization_name' => 'No User Co',
            'legal_name' => 'No User Co S.r.l.',
            'vetting_status' => VettingStatus::Approved,
        ]);

        $this->postJson("/api/v1/admin/partners/{$company->uuid}/impersonate")
            ->assertStatus(422);
    }

    public function test_admin_search_rejects_missing_or_empty_query(): void
    {
        $this->getJson('/api/v1/admin/search')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['q']]]);

        $this->getJson('/api/v1/admin/search?q=')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['q']]]);
    }

    public function test_admin_search_rejects_single_character(): void
    {
        $this->getJson('/api/v1/admin/search?q=S')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['q']]]);
    }

    public function test_admin_search_returns_partner_and_lead_hits(): void
    {
        $sector = Sector::query()->first();

        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Spotlight Partner Co',
            'legal_name' => 'Spotlight Partner Co S.r.l.',
            'city' => 'Torino',
            'vetting_status' => VettingStatus::Approved,
        ]);

        Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-SPOTLIGHT-01',
            'sector_id' => $sector->id,
            'status' => LeadStatus::Processing,
            'payload' => ['autonomy' => 'parziale'],
            'contact_name' => 'Spotlight Lead User',
            'location_label' => 'Torino',
        ]);

        $response = $this->getJson('/api/v1/admin/search?q=Spotlight')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.partners.0.type', 'partner')
            ->assertJsonPath('data.partners.0.id', $company->uuid)
            ->assertJsonPath('data.partners.0.label', 'Spotlight Partner Co')
            ->assertJsonPath('data.leads.0.type', 'lead')
            ->assertJsonPath('data.leads.0.label', 'Spotlight Lead User');

        $this->assertGreaterThanOrEqual(1, count($response->json('data.partners')));
        $this->assertGreaterThanOrEqual(1, count($response->json('data.leads')));
    }

    public function test_admin_search_returns_grouped_results(): void
    {
        $sector = Sector::query()->first();
        $company = Company::query()->first();

        Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-SEARCH-99',
            'sector_id' => $sector->id,
            'status' => LeadStatus::Processing,
            'payload' => ['autonomy' => 'parziale'],
            'contact_name' => 'Searchable Lead User',
            'location_label' => 'Torino',
        ]);

        Transaction::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'TX-SEARCH-99',
            'company_id' => $company->id,
            'wallet_id' => Wallet::query()->create([
                'company_id' => $company->id,
                'balance_credits' => 50,
                'total_spent_credits' => 0,
                'currency' => 'EUR',
            ])->id,
            'type' => TransactionType::Recharge,
            'amount_cents' => 10000,
            'credits_delta' => 100,
            'status' => TransactionStatus::Completed,
            'payment_method' => PaymentMethod::Card,
            'reference' => 'INV-SEARCH-99',
            'completed_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/search?q=Search')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'partners',
                    'leads',
                    'transactions',
                    'advisor_bookings',
                ],
            ]);
    }

    public function test_admin_search_returns_advisor_booking_hit(): void
    {
        $sector = Sector::query()->first();
        $consumer = User::factory()->create([
            'user_type' => UserType::Consumer,
            'email' => 'advisor-search@example.com',
        ]);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'user_id' => $consumer->id,
            'status' => LeadStatus::Processing,
            'title' => 'Ricerca Assistenza Domiciliare',
            'payload' => [],
            'contact_name' => 'Advisor Search User',
        ]);

        $booking = Appointment::query()->create([
            'user_id' => $consumer->id,
            'lead_id' => $lead->id,
            'client_name' => 'Advisor Search User',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '11:00:00',
            'type' => AppointmentType::Advisor,
        ]);

        $response = $this->getJson('/api/v1/admin/search?q=Advisor Search')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.advisor_bookings.0.type', 'advisor_booking')
            ->assertJsonPath('data.advisor_bookings.0.id', (string) $booking->id)
            ->assertJsonPath('data.advisor_bookings.0.label', 'Advisor Search User');

        $this->assertStringContainsString(
            'Ricerca Assistenza Domiciliare',
            (string) $response->json('data.advisor_bookings.0.subtitle'),
        );

        $this->getJson('/api/v1/admin/search?q=Assistenza Domiciliare')
            ->assertOk()
            ->assertJsonPath('data.advisor_bookings.0.id', (string) $booking->id);
    }

    public function test_admin_shows_transaction_detail_by_public_ref(): void
    {
        $company = Company::query()->first();
        $wallet = Wallet::query()->create([
            'company_id' => $company->id,
            'balance_credits' => 100,
            'total_spent_credits' => 0,
            'currency' => 'EUR',
        ]);

        $transaction = Transaction::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'TX-8842',
            'company_id' => $company->id,
            'wallet_id' => $wallet->id,
            'type' => TransactionType::Recharge,
            'amount_cents' => 420000,
            'credits_delta' => 4200,
            'status' => TransactionStatus::Completed,
            'payment_method' => PaymentMethod::Sepa,
            'reference' => 'INV-2026-1842',
            'description' => 'Rinnovo piano Growth',
            'completed_at' => now(),
        ]);

        $this->getJson("/api/v1/admin/transactions/{$transaction->public_ref}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', 'TX-8842')
            ->assertJsonPath('data.partner', $company->organization_name)
            ->assertJsonPath('data.importo', 4200)
            ->assertJsonPath('data.stato', 'Completata')
            ->assertJsonPath('data.metodo', PaymentMethod::Sepa->value)
            ->assertJsonPath('data.riferimento', 'INV-2026-1842')
            ->assertJsonPath('data.note', 'Rinnovo piano Growth');
    }

    public function test_admin_portfolio_summary_from_completed_transactions(): void
    {
        [$milanoCompany, $romaCompany] = $this->seedPortfolioCompanies();

        $this->createCompletedTransaction($milanoCompany, 3_000_000, now()->subMonth()->startOfMonth()->addDays(3));
        $this->createCompletedTransaction($milanoCompany, 10_000_000, now()->startOfMonth()->addDays(2), TransactionType::Recharge);
        $this->createCompletedTransaction($romaCompany, 7_000_000, now()->startOfMonth()->addDay());

        $response = $this->getJson('/api/v1/admin/portfolio/summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_aum',
                    'revenue_under_management',
                    'monthly_growth',
                    'active_contracts',
                    'avg_exposure',
                ],
            ]);

        $this->assertSame('€ 200K', $response->json('data.total_aum'));
        $this->assertSame('€ 100.000', $response->json('data.revenue_under_management'));
        $this->assertSame(2, $response->json('data.active_contracts'));
        $this->assertSame('Medio', $response->json('data.avg_exposure'));
        $this->assertStringContainsString('%', (string) $response->json('data.monthly_growth'));
    }

    public function test_admin_portfolio_allocation_by_sector_region_and_tier(): void
    {
        [$milanoCompany, $romaCompany] = $this->seedPortfolioCompanies();

        $this->createCompletedTransaction($milanoCompany, 60_000_000);
        $this->createCompletedTransaction($romaCompany, 40_000_000);

        $response = $this->getJson('/api/v1/admin/portfolio/allocation')
            ->assertOk()
            ->assertJsonPath('success', true);

        $bySector = collect($response->json('data.by_sector'));
        $this->assertSame(60, $bySector->firstWhere('label', 'Senior Care')['percent'] ?? null);
        $this->assertSame(40, $bySector->firstWhere('label', 'Assistenza domiciliare')['percent'] ?? null);

        $byRegion = collect($response->json('data.by_region'));
        $this->assertSame(60, $byRegion->firstWhere('label', 'Nord')['percent'] ?? null);
        $this->assertSame(40, $byRegion->firstWhere('label', 'Centro')['percent'] ?? null);

        $byTier = collect($response->json('data.by_tier'));
        $this->assertSame(60, $byTier->firstWhere('label', 'Enterprise')['percent'] ?? null);
        $this->assertSame(40, $byTier->firstWhere('label', 'Growth')['percent'] ?? null);
    }

    public function test_admin_portfolio_partners_with_real_aum_and_exposure(): void
    {
        [$milanoCompany, $romaCompany] = $this->seedPortfolioCompanies();

        $this->createCompletedTransaction($milanoCompany, 31_000_000, now()->subMonth()->startOfMonth()->addDays(5));
        $this->createCompletedTransaction($milanoCompany, 5_000_000, now()->startOfMonth()->addDay());
        $this->createCompletedTransaction($romaCompany, 10_000_000, now()->subMonth()->startOfMonth()->addDays(7));
        $this->createCompletedTransaction($romaCompany, 2_000_000, now()->startOfMonth()->addDays(2));

        $response = $this->getJson('/api/v1/admin/portfolio/partners')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.partners');

        $partners = collect($response->json('data.partners'));
        $topPartner = $partners->first();

        $this->assertSame($milanoCompany->uuid, $topPartner['id']);
        $this->assertSame('Portfolio Milano RSA', $topPartner['nome']);
        $this->assertSame('Enterprise', $topPartner['tier']);
        $this->assertSame('€ 360K', $topPartner['aum']);
        $this->assertSame('€ 50K', $topPartner['exposure']);
        $this->assertSame('Milano', $topPartner['citta']);
        $this->assertSame(75, $topPartner['revenue_share']);
        $this->assertCount(6, $topPartner['trend']);
        $this->assertNotEmpty(array_filter($topPartner['trend']));
        $this->assertSame(31_000_000, $topPartner['trend'][4]);
        $this->assertSame(5_000_000, $topPartner['trend'][5]);
    }

    /**
     * @return array{0: Company, 1: Company}
     */
    private function seedPortfolioCompanies(): array
    {
        $seniorCare = Sector::query()->firstOrFail();
        $homeCare = Sector::query()->create([
            'slug' => 'assistenza-domiciliare',
            'name' => 'Assistenza domiciliare',
            'is_active' => true,
        ]);

        $milanoCompany = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $seniorCare->id,
            'organization_name' => 'Portfolio Milano RSA',
            'legal_name' => 'Portfolio Milano RSA S.r.l.',
            'city' => 'Milano',
            'tier' => CompanyTier::Enterprise,
            'vetting_status' => VettingStatus::Approved,
        ]);

        $romaCompany = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $homeCare->id,
            'organization_name' => 'Portfolio Roma Home',
            'legal_name' => 'Portfolio Roma Home S.r.l.',
            'city' => 'Roma',
            'tier' => CompanyTier::Growth,
            'vetting_status' => VettingStatus::Approved,
        ]);

        return [$milanoCompany, $romaCompany];
    }

    private function createCompletedTransaction(
        Company $company,
        int $amountCents,
        ?\DateTimeInterface $completedAt = null,
        TransactionType $type = TransactionType::LeadUnlock,
    ): Transaction {
        $wallet = Wallet::query()->firstOrCreate(
            ['company_id' => $company->id],
            [
                'balance_credits' => 100,
                'total_spent_credits' => 0,
                'currency' => 'EUR',
            ],
        );

        return Transaction::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'TX-'.Str::upper(Str::random(6)),
            'company_id' => $company->id,
            'wallet_id' => $wallet->id,
            'type' => $type,
            'amount_cents' => $amountCents,
            'credits_delta' => (int) ($amountCents / 100),
            'status' => TransactionStatus::Completed,
            'payment_method' => PaymentMethod::Card,
            'reference' => 'REF-'.Str::upper(Str::random(4)),
            'completed_at' => $completedAt ?? now(),
        ]);
    }
}
