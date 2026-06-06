<?php

declare(strict_types=1);

namespace App\Enums;

enum EditorialContentLinkType: string
{
    case Related = 'related';
    case SeeAlso = 'see_also';
    case SeriesNext = 'series_next';
    case SeriesPrev = 'series_prev';
    case StructureProfile = 'structure_profile';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
