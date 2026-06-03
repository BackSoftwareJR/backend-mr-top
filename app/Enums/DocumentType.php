<?php

namespace App\Enums;

enum DocumentType: string
{
    case Visura = 'visura';
    case Identity = 'identity';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
