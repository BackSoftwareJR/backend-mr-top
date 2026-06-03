<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2C;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationsController extends Controller
{
    private const CITIES = [
        ['label' => 'Milano (MI)', 'value' => 'milano-mi'],
        ['label' => 'Roma (RM)', 'value' => 'roma-rm'],
        ['label' => 'Torino (TO)', 'value' => 'torino-to'],
        ['label' => 'Napoli (NA)', 'value' => 'napoli-na'],
        ['label' => 'Bologna (BO)', 'value' => 'bologna-bo'],
        ['label' => 'Firenze (FI)', 'value' => 'firenze-fi'],
    ];

    public function autocomplete(Request $request): JsonResponse
    {
        $q = strtolower((string) $request->query('q', ''));
        $results = array_values(array_filter(
            self::CITIES,
            fn (array $city): bool => $q === '' || str_contains(strtolower($city['label']), $q),
        ));

        return ApiEnvelope::success(['locations' => $results]);
    }
}
