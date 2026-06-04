<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'admin_user_id',
    'company_id',
    'partner_user_id',
    'personal_access_token_id',
    'start_audit_log_id',
    'started_at',
    'expires_at',
    'ended_at',
])]
class ImpersonationSession extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
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
    public function partnerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_user_id');
    }

    /**
     * @return BelongsTo<AuditLog, $this>
     */
    public function startAuditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'start_audit_log_id');
    }

    /**
     * @param  Builder<ImpersonationSession>  $query
     * @return Builder<ImpersonationSession>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
