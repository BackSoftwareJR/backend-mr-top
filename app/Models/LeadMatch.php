<?php

namespace App\Models;

use App\Enums\CrmStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'lead_id',
    'company_id',
    'match_score',
    'rank',
    'is_visible_to_consumer',
    'is_in_marketplace',
    'unlocked_at',
    'unlock_cost_credits',
    'crm_status',
    'assigned_by',
    'metadata',
])]
class LeadMatch extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_score' => 'integer',
            'rank' => 'integer',
            'is_visible_to_consumer' => 'boolean',
            'is_in_marketplace' => 'boolean',
            'unlocked_at' => 'datetime',
            'unlock_cost_credits' => 'integer',
            'crm_status' => CrmStatus::class,
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasMany<SavedMatch, $this>
     */
    public function savedMatches(): HasMany
    {
        return $this->hasMany(SavedMatch::class);
    }
}
