<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'content_id',
    'title',
    'excerpt',
    'body_text',
    'rubric',
    'tags',
    'published_at',
    'indexed_at',
])]
class EditorialSearchDocument extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'published_at' => 'datetime',
            'indexed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EditorialContent, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'content_id');
    }
}
