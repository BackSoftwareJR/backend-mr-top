<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'slug',
    'name',
    'is_active',
    'wizard_schema',
    'operations_schema',
    'trust_schema',
    'matching_rules',
])]
class Sector extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'wizard_schema' => 'array',
            'operations_schema' => 'array',
            'trust_schema' => 'array',
            'matching_rules' => 'array',
        ];
    }

    /**
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * @return BelongsToMany<Company, $this>
     */
    public function multiSectorCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_sectors');
    }

    /**
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * @return HasMany<TrustTest, $this>
     */
    public function trustTests(): HasMany
    {
        return $this->hasMany(TrustTest::class);
    }
}
