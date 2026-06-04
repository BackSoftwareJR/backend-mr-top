<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\ConsentType;
use App\Models\ConsentLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnonymizeConsentLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_command_anonymizes_identifiers_on_expired_consent_logs(): void
    {
        Carbon::setTestNow('2026-06-04 12:00:00');

        $expired = ConsentLog::query()->create([
            'session_id' => 'old-session-001',
            'consent_type' => ConsentType::PrivacyPolicy,
            'policy_version' => '1.0.0',
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Mozilla/5.0 Test Browser',
            'consent_given' => true,
            'consent_text_hash' => str_repeat('a', 64),
        ]);
        $expired->forceFill(['created_at' => now()->subYears(5)->subDay()])->saveQuietly();

        $recent = ConsentLog::query()->create([
            'session_id' => 'recent-session-001',
            'consent_type' => ConsentType::Marketing,
            'policy_version' => '1.0.0',
            'ip_address' => '198.51.100.20',
            'user_agent' => 'Mozilla/5.0 Recent',
            'consent_given' => false,
            'consent_text_hash' => str_repeat('b', 64),
        ]);
        $recent->forceFill(['created_at' => now()->subYears(4)])->saveQuietly();

        $this->artisan('consent-logs:anonymize-retention')
            ->assertSuccessful();

        $expired->refresh();
        $recent->refresh();

        $this->assertNull($expired->ip_address);
        $this->assertNull($expired->user_agent);
        $this->assertNull($expired->session_id);
        $this->assertTrue($expired->consent_given);
        $this->assertSame(str_repeat('a', 64), $expired->consent_text_hash);

        $this->assertSame('198.51.100.20', $recent->ip_address);
        $this->assertSame('Mozilla/5.0 Recent', $recent->user_agent);
        $this->assertSame('recent-session-001', $recent->session_id);
    }

    public function test_dry_run_counts_without_updating(): void
    {
        Carbon::setTestNow('2026-06-04 12:00:00');

        $log = ConsentLog::query()->create([
            'session_id' => 'dry-run-session',
            'consent_type' => ConsentType::AnalyticsCookies,
            'policy_version' => '1.0.0',
            'ip_address' => '203.0.113.99',
            'consent_given' => true,
            'consent_text_hash' => str_repeat('c', 64),
        ]);
        $log->forceFill(['created_at' => now()->subYears(6)])->saveQuietly();

        $this->artisan('consent-logs:anonymize-retention', ['--dry-run' => true])
            ->expectsOutputToContain('Would anonymize 1 consent log(s)')
            ->assertSuccessful();

        $this->assertDatabaseHas('consent_logs', [
            'session_id' => 'dry-run-session',
            'ip_address' => '203.0.113.99',
        ]);
    }

    public function test_retention_period_is_configurable(): void
    {
        Carbon::setTestNow('2026-06-04 12:00:00');
        config(['wenando.consent_log_retention_years' => 3]);

        $log = ConsentLog::query()->create([
            'session_id' => 'config-session',
            'consent_type' => ConsentType::TermsB2c,
            'policy_version' => '1.0.0',
            'ip_address' => '203.0.113.55',
            'consent_given' => true,
            'consent_text_hash' => str_repeat('d', 64),
        ]);
        $log->forceFill(['created_at' => now()->subYears(3)->subDay()])->saveQuietly();

        $this->artisan('consent-logs:anonymize-retention')->assertSuccessful();

        $log->refresh();
        $this->assertNull($log->ip_address);
        $this->assertNull($log->session_id);
    }
}
