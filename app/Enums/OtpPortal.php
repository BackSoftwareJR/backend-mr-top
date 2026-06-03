<?php

namespace App\Enums;

enum OtpPortal: string
{
    case Consumer = 'consumer';
    case Partner = 'partner';
    case Admin = 'admin';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
