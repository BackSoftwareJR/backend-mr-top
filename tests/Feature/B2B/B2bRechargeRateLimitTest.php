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
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class B2bRechargeRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_REQUEST_ID = '01JTESTB2BRECHARGERATELIMIT';

    private Company $company;

    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('b2b-recharge');

        $sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'organization_name' => 'Casa Serenità',
            'legal_name' => 'Casa Serenità S.r.l.',
            'city' => 'Milano',
            'vetting_status' => VettingStatus::Approved,
            'approved_at' => now(),
            'dynamic_attributes' => ['service_areas' => ['Milano']],
        ]);

        Wallet::query()->create([
            'company_id' => $this->company->id,
            'balance_credits' => 150,
            'total_spent_credits' => 0,
            'currency' => 'EUR',
        ]);

        $this->partner = User::factory()->create([
            'user_type' => UserType::B2b,
            'email' => 'partner@struttura.it',
            'password' => Hash::make('password123'),
        ]);

        $this->company->users()->attach($this->partner->id, ['role' => 'owner']);
    }

    public function test_wallet_recharge_returns_rate_limited_on_11th_request_in_window(): void
    {
        Sanctum::actingAs($this->partner);

        $url = '/api/v1/b2b/wallet/recharge';
        $headers = ['X-Request-Id' => self::CLIENT_REQUEST_ID];
        $payload = [
            'amount' => 100,
            'payment_method' => 'card',
        ];

        for ($i = 0; $i < 10; $i++) {
            $headers['Idempotency-Key'] = (string) Str::uuid();

            $this->assertNotSame(429, $this->postJson($url, $payload, $headers)->status());
        }

        $headers['Idempotency-Key'] = (string) Str::uuid();

        $response = $this->postJson($url, $payload, $headers);

        $response->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'RATE_LIMITED')
            ->assertJsonPath('trace_id', self::CLIENT_REQUEST_ID)
            ->assertJsonPath('request_id', self::CLIENT_REQUEST_ID)
            ->assertHeader('X-Request-Id', self::CLIENT_REQUEST_ID);

        $retryAfter = $response->headers->get('Retry-After');
        $this->assertNotNull($retryAfter);
        $this->assertGreaterThan(0, (int) $retryAfter);
    }
}
