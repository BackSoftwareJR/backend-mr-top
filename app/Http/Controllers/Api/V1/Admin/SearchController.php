<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\AdminSearchRequest;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Services\AdminOperationsService;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __construct(
        private readonly AdminOperationsService $adminOps,
    ) {}

    public function index(AdminSearchRequest $request): JsonResponse
    {
        return ApiEnvelope::success(
            $this->adminOps->search($request->validated('q')),
        );
    }
}
