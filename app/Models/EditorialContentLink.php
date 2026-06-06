<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EditorialContentLinkType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'source_content_id',
    'target_content_id',
    'link_type',
    'anchor_text',
    'relevance_score',
])]
class EditorialContentLink extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'link_type' => EditorialContentLinkType::class,
            'relevance_score' => 'float',
        ];
    }

    /**
     * @return BelongsTo<EditorialContent, $this>
     */
    public function sourceContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'source_content_id');
    }

    /**
     * @return BelongsTo<EditorialContent, $this>
     */
    public function targetContent(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'target_content_id');
    }
}
