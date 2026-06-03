<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Concerns\GeneratesUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'wallet_id',
    'lead_match_id',
    'public_ref',
    'type',
    'amount_cents',
    'credits_delta',
    'status',
    'payment_method',
    'reference',
    'description',
    'metadata',
    'completed_at',
])]
class Transaction extends Model
{
    use GeneratesUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount_cents' => 'integer',
            'credits_delta' => 'integer',
            'status' => TransactionStatus::class,
            'payment_method' => PaymentMethod::class,
            'metadata' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return BelongsTo<LeadMatch, $this>
     */
    public function leadMatch(): BelongsTo
    {
        return $this->belongsTo(LeadMatch::class);
    }
}
