<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'user_id',
    'company_id',
    'display_name',
    'role_title',
    'bio',
    'avatar_media_id',
    'credentials',
    'is_active',
])]
class EditorialAuthor extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credentials' => 'array',
            'is_active' => 'boolean',
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
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<EditorialMedia, $this>
     */
    public function avatarMedia(): BelongsTo
    {
        return $this->belongsTo(EditorialMedia::class, 'avatar_media_id');
    }

    /**
     * @return BelongsToMany<EditorialContent, $this>
     */
    public function contents(): BelongsToMany
    {
        return $this->belongsToMany(EditorialContent::class, 'editorial_content_author')
            ->withPivot(['sort_order', 'is_primary'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
