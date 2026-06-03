<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Wallet $wallet */
        $wallet = $this->resource;

        return [
            'balance_credits' => $wallet->balance_credits,
            'total_spent' => $wallet->total_spent_credits,
            'currency' => $wallet->currency,
        ];
    }
}
