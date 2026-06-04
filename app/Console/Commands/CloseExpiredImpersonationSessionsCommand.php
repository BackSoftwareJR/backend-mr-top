<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ImpersonationSessionService;
use Illuminate\Console\Command;

class CloseExpiredImpersonationSessionsCommand extends Command
{
    protected $signature = 'impersonation:close-expired';

    protected $description = 'End impersonation sessions past expires_at and write admin.impersonate.end audit rows';

    public function handle(ImpersonationSessionService $impersonationSessions): int
    {
        $closed = $impersonationSessions->closeExpired();

        $this->components->info(sprintf('Closed %d expired impersonation session(s).', $closed));

        return self::SUCCESS;
    }
}
