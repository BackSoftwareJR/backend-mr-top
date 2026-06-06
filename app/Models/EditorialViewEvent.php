<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'content_id',
    'date',
    'visitor_hash',
    'created_at',
])]
class EditorialViewEvent extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'created_at' => 'datetime',
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
