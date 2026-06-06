<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'editorial_content_id',
    'revision_number',
    'snapshot',
    'body_blocks',
    'seo_pack',
    'created_by_user_id',
    'change_summary',
])]
class EditorialContentRevision extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'body_blocks' => 'array',
            'seo_pack' => 'array',
            'revision_number' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EditorialContent, $this>
     */
    public function editorialContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
