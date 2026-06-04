<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\ImpersonationSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

class ImpersonationSessionService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function start(
        User $admin,
        Company $company,
        User $partnerUser,
        PersonalAccessToken $accessToken,
        AuditLog $startAudit,
        Carbon $expiresAt,
    ): ImpersonationSession {
        return ImpersonationSession::query()->create([
            'admin_user_id' => $admin->id,
            'company_id' => $company->id,
            'partner_user_id' => $partnerUser->id,
            'personal_access_token_id' => $accessToken->id,
            'start_audit_log_id' => $startAudit->id,
            'started_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }

    public function end(
        ImpersonationSession $session,
        string $reason = 'expired',
        ?Request $request = null,
    ): void {
        if (! $session->isActive()) {
            return;
        }

        $session->forceFill(['ended_at' => now()])->save();

        $admin = $session->admin;
        $durationSeconds = (int) $session->started_at->diffInSeconds($session->ended_at);

        $this->auditLogService->record(
            AuditAction::ImpersonateEnd,
            $admin,
            $session->company,
            [
                'admin_uuid' => $admin->uuid,
                'company_uuid' => $session->company->uuid,
                'impersonation_session_id' => $session->id,
                'duration_seconds' => $durationSeconds,
                'reason' => $reason,
            ],
            $request,
        );
    }

    public function endByTokenId(int $tokenId, string $reason = 'token_revoked'): void
    {
        $session = ImpersonationSession::query()
            ->active()
            ->where('personal_access_token_id', $tokenId)
            ->first();

        if ($session === null) {
            return;
        }

        $this->end($session, $reason);
    }

    public function closeExpired(): int
    {
        $closed = 0;

        ImpersonationSession::query()
            ->active()
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->each(function (ImpersonationSession $session) use (&$closed): void {
                $this->end($session, 'expired');
                $closed++;
            });

        return $closed;
    }
}
