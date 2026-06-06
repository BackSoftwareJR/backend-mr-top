<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EditorialAuthorType;
use App\Enums\EditorialContentStatus;
use App\Enums\EditorialContentType;
use App\Models\Concerns\GeneratesUuid;
use Database\Factories\EditorialContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

#[Fillable([
    'slug',
    'content_type',
    'status',
    'title',
    'subtitle',
    'excerpt',
    'body_blocks',
    'type_payload',
    'seo_pack',
    'rubric_slug',
    'rubric_id',
    'tags',
    'sector_id',
    'author_type',
    'author_name',
    'author_role_title',
    'company_id',
    'hero_media_id',
    'word_count',
    'read_minutes',
    'featured',
    'noindex',
    'published_at',
    'scheduled_at',
    'unpublished_at',
    'published_by_user_id',
    'reviewed_at',
    'reviewed_by_user_id',
    'locale',
    'canonical_path',
])]
class EditorialContent extends Model
{
    /** @use HasFactory<EditorialContentFactory> */
    use GeneratesUuid, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_type' => EditorialContentType::class,
            'status' => EditorialContentStatus::class,
            'author_type' => EditorialAuthorType::class,
            'body_blocks' => 'array',
            'type_payload' => 'array',
            'seo_pack' => 'array',
            'tags' => 'array',
            'featured' => 'boolean',
            'noindex' => 'boolean',
            'word_count' => 'integer',
            'read_minutes' => 'integer',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'unpublished_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EditorialRubric, $this>
     */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(EditorialRubric::class, 'rubric_id');
    }

    /**
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<EditorialMedia, $this>
     */
    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(EditorialMedia::class, 'hero_media_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * @return HasMany<EditorialContentRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(EditorialContentRevision::class)->orderByDesc('revision_number');
    }

    /**
     * @return HasMany<EditorialContentSeoAudit, $this>
     */
    public function seoAudits(): HasMany
    {
        return $this->hasMany(EditorialContentSeoAudit::class)->orderByDesc('created_at');
    }

    /**
     * @return HasMany<EditorialIndexQueue, $this>
     */
    public function indexQueueEntries(): HasMany
    {
        return $this->hasMany(EditorialIndexQueue::class);
    }

    /**
     * @return BelongsToMany<EditorialAuthor, $this>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(EditorialAuthor::class, 'editorial_content_author')
            ->withPivot(['sort_order', 'is_primary'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    /**
     * @return HasMany<EditorialContentLink, $this>
     */
    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(EditorialContentLink::class, 'source_content_id');
    }

    /**
     * @return HasMany<EditorialContentLink, $this>
     */
    public function incomingLinks(): HasMany
    {
        return $this->hasMany(EditorialContentLink::class, 'target_content_id');
    }

    /**
     * @return HasMany<EditorialWorkflowEvent, $this>
     */
    public function workflowEvents(): HasMany
    {
        return $this->hasMany(EditorialWorkflowEvent::class, 'content_id')->orderByDesc('created_at');
    }

    /**
     * @return HasOne<EditorialModerationQueue, $this>
     */
    public function moderationQueueEntry(): HasOne
    {
        return $this->hasOne(EditorialModerationQueue::class, 'content_id');
    }

    /**
     * @return HasMany<EditorialSeoGeneration, $this>
     */
    public function seoGenerations(): HasMany
    {
        return $this->hasMany(EditorialSeoGeneration::class, 'content_id')->orderByDesc('created_at');
    }

    /**
     * @return HasOne<EditorialSearchDocument, $this>
     */
    public function searchDocument(): HasOne
    {
        return $this->hasOne(EditorialSearchDocument::class, 'content_id');
    }

    /**
     * @param  Builder<EditorialContent>  $query
     * @return Builder<EditorialContent>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', EditorialContentStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
