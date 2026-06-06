<?php

declare(strict_types=1);

namespace App\Enums;

enum EditorialIndexQueueAction: string
{
    case Index = 'index';
    case Remove = 'remove';
    case Reindex = 'reindex';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
