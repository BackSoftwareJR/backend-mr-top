<?php

namespace App\Models;

use App\Enums\TrustTestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id',
    'sector_id',
    'answers',
    'status',
    'submitted_at',
])]
class TrustTest extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'status' => TrustTestStatus::class,
            'submitted_at' => 'datetime',
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
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * @return HasOne<CompanyTrustScore, $this>
     */
    public function trustScore(): HasOne
    {
        return $this->hasOne(CompanyTrustScore::class);
    }
}
