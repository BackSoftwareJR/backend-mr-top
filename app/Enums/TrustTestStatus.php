<?php

namespace App\Enums;

enum TrustTestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Scored = 'scored';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
