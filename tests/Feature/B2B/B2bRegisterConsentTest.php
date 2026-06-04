<?php

declare(strict_types=1);

namespace Tests\Feature\B2B;

use App\Enums\ConsentType;
use App\Models\ConsentLog;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bRegisterConsentTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVACY_HASH = 'ba8597379922b6fe4154e6448474495391e40e7fa2cd8688f51cf159f7252006';

    protected function setUp(): void
    {
        parent::setUp();

        Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);
    }

    public function test_register_records_privacy_policy_consent_for_new_partner(): void
    {
        $response = $this->withHeaders([
            'User-Agent' => 'WenandoB2BTest/1.0',
        ])->postJson('/api/v1/b2b/register', [
            'email' => 'privacy@struttura.it',
            'organization_name' => 'Privacy Casa',
            'legal_name' => 'Privacy Casa S.r.l.',
            'privacy_policy_accepted' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
            'policy_version' => '1.0.0',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'privacy@struttura.it');

        $user = User::query()->where('email', 'privacy@struttura.it')->firstOrFail();

        $this->assertDatabaseHas('consent_logs', [
            'user_id' => $user->id,
            'consent_type' => ConsentType::PrivacyPolicy->value,
            'policy_version' => '1.0.0',
            'consent_given' => true,
            'consent_text_hash' => self::PRIVACY_HASH,
            'user_agent' => 'WenandoB2BTest/1.0',
        ]);

        $log = ConsentLog::query()
            ->where('user_id', $user->id)
            ->where('consent_type', ConsentType::PrivacyPolicy)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('b2b_register', $log->metadata['source'] ?? null);
        $this->assertNotNull($log->ip_address);
    }

    public function test_register_requires_privacy_policy_acceptance(): void
    {
        $this->postJson('/api/v1/b2b/register', [
            'email' => 'noprivacy@struttura.it',
            'organization_name' => 'No Privacy',
            'legal_name' => 'No Privacy S.r.l.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'privacy_policy_accepted',
                        'consent_text_hash',
                    ],
                ],
            ]);

        $this->assertDatabaseCount('consent_logs', 0);
        $this->assertDatabaseMissing('users', ['email' => 'noprivacy@struttura.it']);
    }
}
