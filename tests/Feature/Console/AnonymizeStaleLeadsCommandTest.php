<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Sector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnonymizeStaleLeadsCommandTest extends TestCase
{
    use RefreshDatabase;

    private Sector $sector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sector = Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_command_anonymizes_stale_leads_with_frozen_dates(): void
    {
        Carbon::setTestNow('2026-06-04 12:00:00');

        $stale = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-STALE',
            'sector_id' => $this->sector->id,
            'status' => LeadStatus::Closed,
            'contact_name' => 'Maria Rossi',
            'contact_phone' => '+39 333 123 4567',
            'contact_email' => 'maria@example.com',
            'location_label' => 'Milano (MI)',
            'need_summary' => 'Assistenza h24',
            'payload' => [
                'autonomy' => 'parziale',
                'contact' => ['nome' => 'Maria Rossi', 'telefono' => '+39 333 123 4567'],
                'location' => ['label' => 'Milano (MI)', 'value' => 'milano-mi'],
            ],
        ]);
        $stale->forceFill(['updated_at' => now()->subDays(731)])->saveQuietly();

        $recent = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-RECENT',
            'sector_id' => $this->sector->id,
            'status' => LeadStatus::Closed,
            'contact_name' => 'Luigi Verdi',
            'contact_email' => 'luigi@example.com',
            'location_label' => 'Roma (RM)',
            'payload' => ['autonomy' => 'parziale'],
        ]);
        $recent->forceFill(['updated_at' => now()->subDays(400)])->saveQuietly();

        $processing = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-PROC',
            'sector_id' => $this->sector->id,
            'status' => LeadStatus::Processing,
            'contact_name' => 'Anna Bianchi',
            'contact_email' => 'anna@example.com',
            'payload' => ['autonomy' => 'parziale'],
        ]);
        $processing->forceFill(['updated_at' => now()->subDays(800)])->saveQuietly();

        $this->artisan('leads:anonymize-stale')->assertSuccessful();

        $stale->refresh();
        $recent->refresh();
        $processing->refresh();

        $this->assertNull($stale->contact_name);
        $this->assertNull($stale->contact_phone);
        $this->assertStringStartsWith('anon_', $stale->contact_email);
        $this->assertStringEndsWith('@anonymized.wenando.local', $stale->contact_email);
        $this->assertSame('Milano', $stale->location_label);
        $this->assertSame('Richiesta anonimizzata', $stale->need_summary);
        $this->assertSame('Ricerca anonimizzata', $stale->title);
        $this->assertArrayNotHasKey('contact', $stale->payload ?? []);
        $this->assertSame('parziale', $stale->payload['autonomy'] ?? null);

        $this->assertSame('Luigi Verdi', $recent->contact_name);
        $this->assertSame('luigi@example.com', $recent->contact_email);
        $this->assertSame('Anna Bianchi', $processing->contact_name);
    }

    public function test_dry_run_counts_without_updating(): void
    {
        Carbon::setTestNow('2026-06-04 12:00:00');

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-DRY',
            'sector_id' => $this->sector->id,
            'status' => LeadStatus::Routed,
            'contact_name' => 'Dry Run',
            'contact_email' => 'dry@example.com',
            'payload' => ['autonomy' => 'parziale'],
        ]);
        $lead->forceFill(['updated_at' => now()->subDays(800)])->saveQuietly();

        $this->artisan('leads:anonymize-stale', ['--dry-run' => true])
            ->expectsOutputToContain('Would anonymize 1 stale lead(s)')
            ->assertSuccessful();

        $this->assertDatabaseHas('leads', [
            'public_ref' => 'LD-DRY',
            'contact_name' => 'Dry Run',
            'contact_email' => 'dry@example.com',
        ]);
    }

    public function test_retention_period_is_configurable(): void
    {
        Carbon::setTestNow('2026-06-04 12:00:00');
        config(['wenando.lead_anonymize_days' => 365]);

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-CFG',
            'sector_id' => $this->sector->id,
            'status' => LeadStatus::Assigned,
            'contact_name' => 'Config Test',
            'contact_email' => 'config@example.com',
            'payload' => ['autonomy' => 'parziale'],
        ]);
        $lead->forceFill(['updated_at' => now()->subDays(366)])->saveQuietly();

        $this->artisan('leads:anonymize-stale')->assertSuccessful();

        $lead->refresh();
        $this->assertNull($lead->contact_name);
        $this->assertStringStartsWith('anon_', $lead->contact_email);
    }

    public function test_already_anonymized_leads_are_skipped(): void
    {
        Carbon::setTestNow('2026-06-04 12:00:00');

        $lead = Lead::query()->create([
            'uuid' => (string) Str::uuid(),
            'public_ref' => 'LD-ANON',
            'sector_id' => $this->sector->id,
            'status' => LeadStatus::Cancelled,
            'contact_email' => 'anon_deadbeef@anonymized.wenando.local',
            'contact_name' => null,
            'payload' => ['autonomy' => 'parziale'],
        ]);
        $lead->forceFill(['updated_at' => now()->subDays(900)])->saveQuietly();

        $this->artisan('leads:anonymize-stale', ['--dry-run' => true])
            ->expectsOutputToContain('Would anonymize 0 stale lead(s)')
            ->assertSuccessful();
    }
}
