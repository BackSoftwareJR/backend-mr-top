<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id',
    'trust_test_id',
    'score',
    'breakdown',
    'scored_at',
])]
class CompanyTrustScore extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'breakdown' => 'array',
            'scored_at' => 'datetime',
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
     * @return BelongsTo<TrustTest, $this>
     */
    public function trustTest(): BelongsTo
    {
        return $this->belongsTo(TrustTest::class);
    }
}
