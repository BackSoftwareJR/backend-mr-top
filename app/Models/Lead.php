<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Models\Concerns\GeneratesUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'sector_id',
    'user_id',
    'public_ref',
    'status',
    'admin_status',
    'payload',
    'contact_name',
    'contact_phone',
    'contact_email',
    'location_label',
    'budget_min',
    'budget_max',
    'need_summary',
    'admin_notes',
])]
class Lead extends Model
{
    use GeneratesUuid, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'payload' => 'array',
            'budget_min' => 'integer',
            'budget_max' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<LeadMatch, $this>
     */
    public function leadMatches(): HasMany
    {
        return $this->hasMany(LeadMatch::class);
    }

    /**
     * @return HasMany<ConsentLog, $this>
     */
    public function consentLogs(): HasMany
    {
        return $this->hasMany(ConsentLog::class);
    }
}
