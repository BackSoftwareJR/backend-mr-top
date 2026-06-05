<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Services\ItalianGeoSearchService;
use App\Services\PhotonGeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    public function __construct(
        private readonly ItalianGeoSearchService $geoSearch,
        private readonly PhotonGeocodingService $photon,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $limit = (int) $request->query('limit', 10);
        $country = (string) $request->query('country', 'it');

        $results = $this->geoSearch->search($query, $limit, $country);

        return ApiEnvelope::success(['results' => $results]);
    }

    public function reverse(Request $request): JsonResponse
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            abort(422, 'Coordinate non valide.');
        }

        $result = $this->photon->reverse((float) $lat, (float) $lng);

        if ($result === null) {
            abort(404, 'Località non trovata.');
        }

        return ApiEnvelope::success([
            'label' => $result['label'],
            'lat' => $result['lat'],
            'lng' => $result['lng'],
            'meta' => $result['meta'],
        ]);
    }
}
