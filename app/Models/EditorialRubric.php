<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'parent_id',
    'slug',
    'name',
    'description',
    'sort_order',
    'is_active',
    'default_index_rules',
])]
class EditorialRubric extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'default_index_rules' => 'array',
        ];
    }

    /**
     * @return BelongsTo<EditorialRubric, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<EditorialRubric, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<EditorialContent, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(EditorialContent::class, 'rubric_id');
    }
}
