<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'content_id',
    'date',
    'page_views',
    'unique_visitors',
    'bot_views',
])]
class EditorialContentDailyStat extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'page_views' => 'integer',
            'unique_visitors' => 'integer',
            'bot_views' => 'integer',
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
