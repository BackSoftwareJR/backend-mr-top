<?php

declare(strict_types=1);

namespace App\Enums;

enum EditorialNotificationType: string
{
    case PendingReview = 'editorial.pending_review';
    case ReviewOutcome = 'editorial.review_outcome';
    case SeoNeedsReview = 'editorial.seo_needs_review';
    case ReviewDigest = 'editorial.review_digest';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'Nuovo contenuto in revisione',
            self::ReviewOutcome => 'Esito revisione editoriale',
            self::SeoNeedsReview => 'SEO da rivedere',
            self::ReviewDigest => 'Riepilogo revisioni editoriali',
        };
    }
}
