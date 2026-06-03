<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2C;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\Sector;
use Illuminate\Http\JsonResponse;

class WizardController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $sector = Sector::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $schema = $sector->wizard_schema ?? [];

        return ApiEnvelope::success($schema);
    }

    public function sectors(): JsonResponse
    {
        $sectors = Sector::query()
            ->where('is_active', true)
            ->get(['slug', 'name'])
            ->map(fn (Sector $s) => ['slug' => $s->slug, 'name' => $s->name])
            ->values()
            ->all();

        return ApiEnvelope::success(['sectors' => $sectors]);
    }
}
