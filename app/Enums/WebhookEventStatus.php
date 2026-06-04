<?php

declare(strict_types=1);

namespace App\Enums;

enum WebhookEventStatus: string
{
    case Processed = 'processed';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
