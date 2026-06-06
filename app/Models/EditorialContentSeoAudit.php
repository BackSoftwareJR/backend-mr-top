<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'editorial_content_id',
    'revision_number',
    'seo_pack',
    'seo_score',
    'approved',
    'approved_by_user_id',
    'approved_at',
    'auditor_notes',
])]
class EditorialContentSeoAudit extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seo_pack' => 'array',
            'seo_score' => 'integer',
            'revision_number' => 'integer',
            'approved' => 'boolean',
            'approved_at' => 'datetime',
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
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
