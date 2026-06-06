<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EditorialModerationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'content_id',
    'company_id',
    'assigned_reviewer_id',
    'status',
    'notes',
    'submitted_by_user_id',
    'submitted_at',
    'resolved_at',
])]
class EditorialModerationQueue extends Model
{
    protected $table = 'editorial_moderation_queue';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EditorialModerationStatus::class,
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
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
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_reviewer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
