<?php

namespace App\Enums;

enum LeadStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Routed = 'routed';
    case Assigned = 'assigned';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
