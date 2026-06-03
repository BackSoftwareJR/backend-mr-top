<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ApiEnvelope;
use App\Models\Transaction;
use App\Services\AdminOperationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionsController extends Controller
{
    public function __construct(
        private readonly AdminOperationsService $adminOps,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiEnvelope::success(
            $this->adminOps->transactions(
                $request->query('status'),
                (int) $request->integer('per_page', 20),
            ),
        );
    }

    public function show(Transaction $transaction): JsonResponse
    {
        return ApiEnvelope::success(
            $this->adminOps->transactionDetail($transaction),
        );
    }
}
