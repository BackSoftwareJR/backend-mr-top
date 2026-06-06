<?php

declare(strict_types=1);

namespace App\Enums;

enum EditorialAuthorType: string
{
    case Admin = 'admin';
    case Company = 'company';
    case Agent = 'agent';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
