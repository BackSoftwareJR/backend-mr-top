<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class NotImplementedController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'NOT_IMPLEMENTED',
                'message' => 'Endpoint non ancora implementato.',
            ],
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
}
