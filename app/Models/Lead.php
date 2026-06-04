<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Models\Concerns\GeneratesUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
    'title',
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

        if (Str::isUuid($ref)) {
            return static::query()->where('uuid', $ref)->first();
        }

        return null;
    }

    public function displayTitle(): string
    {
        if ($this->title !== null && $this->title !== '') {
            return $this->title;
        }

        if ($this->need_summary !== null && $this->need_summary !== '') {
            return $this->need_summary;
        }

        return 'Ricerca assistenza';
    }
}
