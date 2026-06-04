<?php

namespace App\Models;

use App\Enums\CrmStatus;
use App\Support\MarketplaceRef;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'lead_id',
    'company_id',
    'public_ref',
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

    protected static function booted(): void
    {
        static::created(function (LeadMatch $match): void {
            if ($match->public_ref !== null) {
                return;
            }

            $match->forceFill([
                'public_ref' => sprintf('ML-%d', $match->id),
            ])->saveQuietly();
        });
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

        return static::findByExternalRef((string) $value);
    }

    public static function findByExternalRef(string $ref): ?static
    {
        if ($ref === '') {
            return null;
        }

        $byPublicRef = static::query()->where('public_ref', $ref)->first();

        if ($byPublicRef !== null) {
            return $byPublicRef;
        }

        $matchId = MarketplaceRef::parseMatchId($ref);

        if ($matchId !== null) {
            return static::query()->find($matchId);
        }

        $crmId = MarketplaceRef::parseCrmClientId($ref);

        if ($crmId !== null) {
            return static::query()->find($crmId);
        }

        return null;
    }

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
