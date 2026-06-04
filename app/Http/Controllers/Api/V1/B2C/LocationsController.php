<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\B2C;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Services\ItalianCitiesAutocompleteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationsController extends Controller
{
    public function __construct(
        private readonly ItalianCitiesAutocompleteService $cities,
    ) {}

    public function autocomplete(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $suggestions = $this->cities->search($query);

        return ApiEnvelope::success(['suggestions' => $suggestions]);
    }
}
