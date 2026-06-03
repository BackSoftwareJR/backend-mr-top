<?php

declare(strict_types=1);

namespace App\Exceptions;

class InsufficientCreditsException extends ApiException
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(?array $details = null)
    {
        parent::__construct(
            'INSUFFICIENT_CREDITS',
            'Credito insufficiente. Ricarica il wallet per sbloccare il lead.',
            402,
            $details,
        );
    }
}
