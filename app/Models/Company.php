<?php

namespace App\Models;

use App\Enums\CompanyTier;
use App\Enums\VettingStatus;
use App\Models\Concerns\GeneratesUuid;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'sector_id',
    'organization_name',
    'legal_name',
    'vat_number',
    'sdi_code',
    'city',
    'vetting_status',
    'tier',
    'dynamic_attributes',
    'schedule',
    'approved_at',
    'rejected_at',
    'rejection_reason',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use GeneratesUuid, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vetting_status' => VettingStatus::class,
            'tier' => CompanyTier::class,
            'dynamic_attributes' => 'array',
            'schedule' => 'array',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
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
     * @return BelongsToMany<Sector, $this>
     */
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'company_sectors');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->withPivot('role')
            ->withTimestamps()
            ->using(CompanyUser::class);
    }

    /**
     * @return HasMany<CompanyDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CompanyDocument::class);
    }

    /**
     * @return HasMany<TrustTest, $this>
     */
    public function trustTests(): HasMany
    {
        return $this->hasMany(TrustTest::class);
    }

    /**
     * @return HasMany<CompanyTrustScore, $this>
     */
    public function trustScores(): HasMany
    {
        return $this->hasMany(CompanyTrustScore::class);
    }

    /**
     * @return HasOne<CompanyTrustScore, $this>
     */
    public function latestTrustScore(): HasOne
    {
        return $this->hasOne(CompanyTrustScore::class)->latestOfMany('scored_at');
    }

    /**
     * @return HasMany<LeadMatch, $this>
     */
    public function leadMatches(): HasMany
    {
        return $this->hasMany(LeadMatch::class);
    }

    /**
     * @return HasOne<CompanyProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(CompanyProfile::class);
    }

    /**
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
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

    /**
     * @return MorphMany<Notification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }
}
