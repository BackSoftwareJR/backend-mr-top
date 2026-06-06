<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\GeneratesUuid;
use Database\Factories\EditorialMediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'disk',
    'path',
    'mime_type',
    'width',
    'height',
    'alt_text',
    'caption',
    'credit',
    'focal_point',
    'uploaded_by_user_id',
])]
class EditorialMedia extends Model
{
    /** @use HasFactory<EditorialMediaFactory> */
    use GeneratesUuid, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'focal_point' => 'array',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * @return HasMany<EditorialContent, $this>
     */
    public function heroContents(): HasMany
    {
        return $this->hasMany(EditorialContent::class, 'hero_media_id');
    }
}
