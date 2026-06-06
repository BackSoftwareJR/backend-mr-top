<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EditorialSeoGenerationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'content_id',
    'groq_payload',
    'seo_pack',
    'score',
    'status',
    'groq_model',
    'prompt_version',
    'latency_ms',
    'error_message',
    'reviewed_by_user_id',
    'reviewed_at',
])]
class EditorialSeoGeneration extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'groq_payload' => 'array',
            'seo_pack' => 'array',
            'score' => 'integer',
            'status' => EditorialSeoGenerationStatus::class,
            'latency_ms' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EditorialContent, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(EditorialContent::class, 'content_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
