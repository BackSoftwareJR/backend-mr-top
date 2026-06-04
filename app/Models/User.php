<?php

namespace App\Models;

use App\Enums\UserType;
use App\Models\Concerns\GeneratesUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'phone',
    'password',
    'user_type',
    'email_verified_at',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use GeneratesUuid, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_type' => UserType::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot('role')
            ->withTimestamps()
            ->using(CompanyUser::class);
    }

    /**
     * @return HasMany<ConsentLog, $this>
     */
    public function consentLogs(): HasMany
    {
        return $this->hasMany(ConsentLog::class);
    }

    /**
     * @return HasMany<DataErasureRequest, $this>
     */
    public function dataErasureRequests(): HasMany
    {
        return $this->hasMany(DataErasureRequest::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->morphToMany(Role::class, 'model', 'model_has_roles', 'model_id', 'role_id')
            ->withPivot('company_id');
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->morphToMany(Permission::class, 'model', 'model_has_permissions', 'model_id', 'permission_id');
    }

    /**
     * @return HasMany<LeadMatch, $this>
     */
    public function assignedLeadMatches(): HasMany
    {
        return $this->hasMany(LeadMatch::class, 'assigned_by');
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

    /**
     * @return HasMany<CompanyDocument, $this>
     */
    public function verifiedDocuments(): HasMany
    {
        return $this->hasMany(CompanyDocument::class, 'verified_by');
    }

    /**
     * @return MorphMany<Notification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    public function getOnboardingStatusAttribute(): ?string
    {
        if ($this->user_type !== UserType::B2b) {
            return null;
        }

        return $this->companies()->first()?->vetting_status?->value;
    }
}
