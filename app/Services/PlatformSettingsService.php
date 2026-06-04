<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class PlatformSettingsService
{
    public const ADMIN_KEY = 'admin';

    /** @deprecated Legacy cache key; migrated to DB on first read. */
    public const LEGACY_CACHE_KEY = 'admin.platform_settings';

    /**
     * @return array<string, mixed>
     */
    public function defaultSettings(): array
    {
        return [
            'security' => ['otp_ttl_minutes' => 10],
            'automations' => ['auto_match_on_lead' => true],
            'notifications' => ['admin_email' => config('mail.from.address')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $row = PlatformSetting::query()
            ->where('key', self::ADMIN_KEY)
            ->first();

        if ($row !== null) {
            return $row->value;
        }

        $legacy = Cache::get(self::LEGACY_CACHE_KEY);

        if (is_array($legacy)) {
            $this->persist($legacy);
            Cache::forget(self::LEGACY_CACHE_KEY);

            return $legacy;
        }

        $defaults = $this->defaultSettings();
        $this->persist($defaults);

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $partial
     * @return array<string, mixed>
     */
    public function update(array $partial): array
    {
        $merged = array_replace_recursive($this->get(), $partial);
        $this->persist($merged);
        Cache::forget(self::LEGACY_CACHE_KEY);

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function persist(array $settings): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['key' => self::ADMIN_KEY],
            ['value' => $settings],
        );
    }
}
