<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\Sector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = Cache::get('admin.platform_settings', [
            'security' => ['otp_ttl_minutes' => 10],
            'automations' => ['auto_match_on_lead' => true],
            'notifications' => ['admin_email' => config('mail.from.address')],
        ]);

        return ApiEnvelope::success($settings);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'security' => ['sometimes', 'array'],
            'automations' => ['sometimes', 'array'],
            'notifications' => ['sometimes', 'array'],
        ]);

        $current = Cache::get('admin.platform_settings', []);
        $merged = array_merge($current, $validated);
        Cache::forever('admin.platform_settings', $merged);

        return ApiEnvelope::success(['settings' => $merged]);
    }

    public function sectors(): JsonResponse
    {
        $sectors = Sector::query()->get()->map(fn (Sector $s) => [
            'id' => $s->id,
            'slug' => $s->slug,
            'name' => $s->name,
            'is_active' => $s->is_active,
        ])->all();

        return ApiEnvelope::success(['sectors' => $sectors]);
    }

    public function updateSector(Request $request, int $id): JsonResponse
    {
        $sector = Sector::query()->findOrFail($id);
        $validated = $request->validate([
            'wizard_schema' => ['sometimes', 'array'],
            'matching_rules' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $sector->update($validated);

        return ApiEnvelope::success(['sector' => $sector->fresh()]);
    }

    public function notifications(): JsonResponse
    {
        return ApiEnvelope::success(['notifications' => []]);
    }
}
