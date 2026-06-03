<?php

declare(strict_types=1);

namespace App\Exceptions;

class PartnerNotApprovedException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'PARTNER_NOT_APPROVED',
            'Completa onboarding e attendi approvazione.',
            403,
        );
    }
}
