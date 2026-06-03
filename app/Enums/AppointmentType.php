<?php

namespace App\Enums;

enum AppointmentType: string
{
    case Visit = 'visit';
    case Advisor = 'advisor';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
