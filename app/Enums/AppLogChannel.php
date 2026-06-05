<?php

declare(strict_types=1);

namespace App\Enums;

enum AppLogChannel: string
{
    case Api = 'api';
    case Job = 'job';
    case Webhook = 'webhook';
    case Calendar = 'calendar';
    case B2b = 'b2b';
}
