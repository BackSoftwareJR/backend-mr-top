<?php

declare(strict_types=1);

namespace App\Exceptions;

class AlreadyUnlockedException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'ALREADY_UNLOCKED',
            'Lead già sbloccato.',
            409,
        );
    }
}
