<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\GeneratesUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'public_ref',
    'company_id',
    'amount_cents',
    'credits',
    'status',
    'payment_method',
    'provider',
    'provider_ref',
    'idempotency_key',
    'client_secret',
    'transaction_id',
    'completed_at',
])]
class PaymentIntent extends Model
{
    use GeneratesUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'credits' => 'integer',
            'status' => PaymentIntentStatus::class,
            'payment_method' => PaymentMethod::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        $query = static::query()
            ->where('public_ref', $value)
            ->orWhere('uuid', $value);

        if (is_numeric($value)) {
            $query->orWhere('id', (int) $value);
        }

        return $query->first();
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
