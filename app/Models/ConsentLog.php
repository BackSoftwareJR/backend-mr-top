<?php

namespace App\Models;

use App\Enums\ConsentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'lead_id',
    'session_id',
    'consent_type',
    'policy_version',
    'ip_address',
    'user_agent',
    'consent_given',
    'consent_text_hash',
    'metadata',
])]
class ConsentLog extends Model
{
    use SoftDeletes;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consent_type' => ConsentType::class,
            'consent_given' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @param  Builder<ConsentLog>  $query
     * @return Builder<ConsentLog>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * @param  Builder<ConsentLog>  $query
     * @return Builder<ConsentLog>
     */
    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * @param  Builder<ConsentLog>  $query
     * @return Builder<ConsentLog>
     */
    public function scopeOfType(Builder $query, ConsentType|string $type): Builder
    {
        $value = $type instanceof ConsentType ? $type->value : $type;

        return $query->where('consent_type', $value);
    }

    /**
     * @param  Builder<ConsentLog>  $query
     * @return Builder<ConsentLog>
     */
    public function scopeGiven(Builder $query): Builder
    {
        return $query->where('consent_given', true);
    }

    /**
     * @param  Builder<ConsentLog>  $query
     * @return Builder<ConsentLog>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
