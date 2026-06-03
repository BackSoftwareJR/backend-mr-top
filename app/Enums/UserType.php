<?php

namespace App\Enums;

enum UserType: string
{
    case Consumer = 'consumer';
    case B2b = 'b2b';
    case Superadmin = 'superadmin';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
