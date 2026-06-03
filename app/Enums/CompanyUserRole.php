<?php

namespace App\Enums;

enum CompanyUserRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
