<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\CrmStatus;
use App\Models\Company;
use App\Models\LeadMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class CrmService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ActivityFeedService $activityFeedService,
    ) {}

    /**
     * @return Collection<int, LeadMatch>
     */
    public function listClients(Company $company, ?CrmStatus $status = null): Collection
    {
        $query = LeadMatch::query()
            ->with('lead')
            ->where('company_id', $company->id)
            ->whereNotNull('unlocked_at')
            ->orderByDesc('unlocked_at');

        if ($status !== null) {
            $query->where('crm_status', $status);
        }

        return $query->get();
    }

    public function updateStatus(
        Company $company,
        string $clientRef,
        CrmStatus $status,
        User $actor,
        ?Request $request = null,
    ): LeadMatch {
        $leadMatch = $this->findUnlockedClient($company, $clientRef);
        $oldStatus = $leadMatch->crm_status;

        $leadMatch->forceFill(['crm_status' => $status])->save();
        $leadMatch = $leadMatch->fresh(['lead']);

        $metadata = [
            'company_id' => $company->id,
            'old_status' => $oldStatus?->value,
            'new_status' => $status->value,
            'public_ref' => $leadMatch->public_ref,
        ];

        if ($request !== null) {
            $metadata['user_agent'] = $this->auditLogService->truncateUserAgent($request->userAgent());
            $metadata['request_method'] = $request->method();
            $metadata['request_path'] = $request->path();
        }

        $this->auditLogService->record(
            AuditAction::CrmStatusUpdated,
            $actor,
            $leadMatch,
            $metadata,
            $request,
        );

        $this->activityFeedService->recordCrmStatusChange(
            $company,
            $leadMatch,
            $oldStatus,
            $status,
        );

        return $leadMatch;
    }

    private function findUnlockedClient(Company $company, string $clientRef): LeadMatch
    {
        $leadMatch = LeadMatch::findByExternalRef($clientRef);

        if ($leadMatch === null) {
            abort(404, 'Cliente CRM non trovato.');
        }

        return LeadMatch::query()
            ->with('lead')
            ->where('company_id', $company->id)
            ->whereKey($leadMatch->id)
            ->whereNotNull('unlocked_at')
            ->firstOrFail();
    }
}
