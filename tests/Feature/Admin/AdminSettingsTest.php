<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserType;
use App\Models\PlatformSetting;
use App\Models\Sector;
use App\Models\User;
use App\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(PlatformSettingsService::LEGACY_CACHE_KEY);

        $this->admin = User::factory()->create([
            'user_type' => UserType::Superadmin,
            'email' => 'admin@wenando.com',
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_admin_get_settings_returns_defaults(): void
    {
        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'security' => ['otp_ttl_minutes'],
                    'automations' => ['auto_match_on_lead'],
                    'notifications' => ['admin_email'],
                ],
            ])
            ->assertJsonPath('data.security.otp_ttl_minutes', 10)
            ->assertJsonPath('data.automations.auto_match_on_lead', true);

        $this->assertDatabaseHas('platform_settings', [
            'key' => PlatformSettingsService::ADMIN_KEY,
        ]);
    }

    public function test_admin_patch_settings_merges_and_persists(): void
    {
        $this->patchJson('/api/v1/admin/settings', [
            'security' => ['otp_ttl_minutes' => 15],
            'notifications' => ['admin_email' => 'ops@wenando.com'],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.settings.security.otp_ttl_minutes', 15)
            ->assertJsonPath('data.settings.notifications.admin_email', 'ops@wenando.com')
            ->assertJsonPath('data.settings.automations.auto_match_on_lead', true);

        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.security.otp_ttl_minutes', 15)
            ->assertJsonPath('data.notifications.admin_email', 'ops@wenando.com');

        $row = PlatformSetting::query()
            ->where('key', PlatformSettingsService::ADMIN_KEY)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(15, $row->value['security']['otp_ttl_minutes']);
        $this->assertSame('ops@wenando.com', $row->value['notifications']['admin_email']);
        $this->assertTrue($row->value['automations']['auto_match_on_lead']);
    }

    public function test_settings_persist_after_cache_cleared(): void
    {
        $this->patchJson('/api/v1/admin/settings', [
            'security' => ['otp_ttl_minutes' => 20],
        ])->assertOk();

        Cache::flush();

        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.security.otp_ttl_minutes', 20);
    }

    public function test_legacy_cache_migrates_to_database_on_read(): void
    {
        $legacy = [
            'security' => ['otp_ttl_minutes' => 99],
            'automations' => ['auto_match_on_lead' => false],
            'notifications' => ['admin_email' => 'legacy@wenando.com'],
        ];

        Cache::forever(PlatformSettingsService::LEGACY_CACHE_KEY, $legacy);

        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.security.otp_ttl_minutes', 99)
            ->assertJsonPath('data.notifications.admin_email', 'legacy@wenando.com');

        $this->assertDatabaseHas('platform_settings', [
            'key' => PlatformSettingsService::ADMIN_KEY,
        ]);

        $this->assertNull(Cache::get(PlatformSettingsService::LEGACY_CACHE_KEY));

        Cache::flush();

        $this->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.security.otp_ttl_minutes', 99);
    }

    public function test_admin_lists_sectors(): void
    {
        Sector::query()->create([
            'slug' => 'senior-care',
            'name' => 'Senior Care',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/admin/sectors')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'sectors' => [
                        '*' => ['id', 'slug', 'name', 'is_active'],
                    ],
                ],
            ])
            ->assertJsonPath('data.sectors.0.slug', 'senior-care');
    }

    public function test_admin_updates_sector_is_active(): void
    {
        $sector = Sector::query()->create([
            'slug' => 'centro-diurno',
            'name' => 'Centro diurno',
            'is_active' => true,
        ]);

        $this->patchJson("/api/v1/admin/sectors/{$sector->id}", [
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sector.is_active', false);

        $this->assertDatabaseHas('sectors', [
            'id' => $sector->id,
            'is_active' => false,
        ]);
    }
}
