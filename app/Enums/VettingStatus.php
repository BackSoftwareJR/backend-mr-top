<?php

namespace App\Enums;

enum VettingStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
