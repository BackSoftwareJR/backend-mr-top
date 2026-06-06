<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EditorialIndexQueueAction;
use App\Enums\EditorialIndexQueueStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'editorial_content_id',
    'action',
    'status',
    'scheduled_at',
    'processed_at',
    'error_message',
])]
class EditorialIndexQueue extends Model
{
    protected $table = 'editorial_index_queue';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => EditorialIndexQueueAction::class,
            'status' => EditorialIndexQueueStatus::class,
            'scheduled_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EditorialContent, $this>
     */
    public function editorialContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class);
    }
}
