<?php

declare(strict_types=1);

namespace App\Enums;

enum EditorialContentType: string
{
    case Article = 'article';
    case Story = 'story';
    case Interview = 'interview';
    case Event = 'event';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
