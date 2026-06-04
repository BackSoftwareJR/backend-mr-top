<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\ImpersonationSessionService;
use Laravel\Sanctum\PersonalAccessToken;

class PersonalAccessTokenObserver
{
    public function __construct(
        private readonly ImpersonationSessionService $impersonationSessionService,
    ) {}

    public function deleting(PersonalAccessToken $token): void
    {
        if ($token->name !== 'impersonation') {
            return;
        }

        $this->impersonationSessionService->endByTokenId($token->id, 'token_revoked');
    }
}
