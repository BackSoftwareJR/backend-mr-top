<?php

namespace App\Enums;

enum CompanyTier: string
{
    case Starter = 'starter';
    case Growth = 'growth';
    case Enterprise = 'enterprise';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
