<?php

declare(strict_types=1);

namespace App\Enums;

enum EditorialModerationStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case RevisionRequested = 'revision_requested';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
