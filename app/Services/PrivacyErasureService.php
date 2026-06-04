<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\DataErasureRequestStatus;
use App\Exceptions\ApiException;
use App\Jobs\ProcessDataErasureRequest;
use App\Mail\PrivacyErasureRequestMail;
use App\Models\DataErasureRequest;
use App\Models\LeadMatch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PrivacyErasureService
{
    private const ANONYMIZED_USER_EMAIL_DOMAIN = 'anonymized.wenando.local';

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly LeadAnonymizationService $leadAnonymizationService,
    ) {}

    /**
     * @return array{erasure_request: DataErasureRequest}
     */
    public function submitEraseRequest(User $user, ?string $reason, Request $request): array
    {
        return DB::transaction(function () use ($user, $reason, $request): array {
            $pending = DataErasureRequest::query()
                ->where('user_id', $user->id)
                ->where('status', DataErasureRequestStatus::Pending)
                ->first();

            if ($pending !== null) {
                throw new ApiException(
                    'ERASURE_REQUEST_PENDING',
                    'Hai già una richiesta di cancellazione in elaborazione.',
                    409,
                );
            }

            $erasureRequest = DataErasureRequest::query()->create([
                'user_id' => $user->id,
                'status' => DataErasureRequestStatus::Pending,
                'reason' => $reason,
                'ip_address' => $request->ip(),
                'user_agent' => $this->auditLogService->truncateUserAgent($request->userAgent()),
                'requested_at' => now(),
                'metadata' => [
                    'channel' => 'api',
                    'sla_days' => 30,
                ],
            ]);

            $this->auditLogService->record(
                AuditAction::PrivacyEraseRequest,
                $user,
                $erasureRequest,
                ['reason' => $reason],
                $request,
            );

            $this->notifyPrivacyContactOfErasureRequest($erasureRequest, $user);

            return ['erasure_request' => $erasureRequest];
        });
    }

    /**
     * @return array{
     *     erasure_requests: list<array<string, mixed>>,
     *     pending_count: int,
     *     processing_count: int
     * }
     */
    public function listAdminQueue(int $perPage = 20): array
    {
        $statuses = [DataErasureRequestStatus::Pending, DataErasureRequestStatus::Processing];

        $requests = DataErasureRequest::query()
            ->with('user')
            ->whereIn('status', $statuses)
            ->orderByDesc('requested_at')
            ->limit($perPage)
            ->get()
            ->map(fn (DataErasureRequest $request) => $this->formatAdminErasureRequest($request))
            ->all();

        return [
            'erasure_requests' => $requests,
            'pending_count' => DataErasureRequest::query()
                ->where('status', DataErasureRequestStatus::Pending)
                ->count(),
            'processing_count' => DataErasureRequest::query()
                ->where('status', DataErasureRequestStatus::Processing)
                ->count(),
        ];
    }

    /**
     * @return array{erasure_request: DataErasureRequest}
     */
    public function reviewErasureRequest(
        DataErasureRequest $erasureRequest,
        string $action,
        User $admin,
        ?string $notes,
        ?Request $request = null,
    ): array {
        return match ($action) {
            'approve' => $this->approveErasureRequest($erasureRequest, $admin, $notes, $request),
            'reject' => $this->rejectErasureRequest($erasureRequest, $admin, $notes, $request),
            'review' => $this->markErasureRequestReviewed($erasureRequest, $admin, $notes, $request),
            default => throw new ApiException(
                'ERASURE_INVALID_ACTION',
                'Azione non valida per la richiesta di cancellazione.',
                422,
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function formatAdminErasureRequest(DataErasureRequest $erasureRequest): array
    {
        $user = $erasureRequest->user;

        return [
            'id' => $erasureRequest->id,
            'status' => $erasureRequest->status->value,
            'reason' => $erasureRequest->reason,
            'requested_at' => $erasureRequest->requested_at?->toIso8601String(),
            'processed_at' => $erasureRequest->processed_at?->toIso8601String(),
            'metadata' => $erasureRequest->metadata ?? [],
            'user' => $user !== null ? [
                'id' => $user->uuid,
                'email' => $user->email,
                'name' => $user->name,
            ] : null,
        ];
    }

    /**
     * @return array{erasure_request: DataErasureRequest}
     */
    private function approveErasureRequest(
        DataErasureRequest $erasureRequest,
        User $admin,
        ?string $notes,
        ?Request $request,
    ): array {
        if (! in_array($erasureRequest->status, [DataErasureRequestStatus::Pending, DataErasureRequestStatus::Processing], true)) {
            throw new ApiException(
                'ERASURE_NOT_REVIEWABLE',
                'La richiesta non è più in coda di revisione.',
                422,
            );
        }

        $metadata = array_merge($erasureRequest->metadata ?? [], [
            'admin_approved_at' => now()->toIso8601String(),
            'admin_uuid' => $admin->uuid,
            'admin_notes' => $notes,
        ]);

        if ($erasureRequest->status === DataErasureRequestStatus::Pending) {
            $erasureRequest->update(['metadata' => $metadata]);
            ProcessDataErasureRequest::dispatch($erasureRequest->id);
        } else {
            $erasureRequest->update(['metadata' => $metadata]);
        }

        $this->auditLogService->record(
            AuditAction::PrivacyErasureApproved,
            $admin,
            $erasureRequest->fresh(),
            [
                'admin_uuid' => $admin->uuid,
                'erasure_request_id' => $erasureRequest->id,
                'notes' => $notes,
            ],
            $request,
        );

        return ['erasure_request' => $erasureRequest->fresh()];
    }

    /**
     * @return array{erasure_request: DataErasureRequest}
     */
    private function rejectErasureRequest(
        DataErasureRequest $erasureRequest,
        User $admin,
        ?string $notes,
        ?Request $request,
    ): array {
        if ($erasureRequest->status !== DataErasureRequestStatus::Pending) {
            throw new ApiException(
                'ERASURE_NOT_REJECTABLE',
                'Solo le richieste in attesa possono essere rifiutate.',
                422,
            );
        }

        $erasureRequest->update([
            'status' => DataErasureRequestStatus::Rejected,
            'processed_at' => now(),
            'metadata' => array_merge($erasureRequest->metadata ?? [], [
                'admin_rejected_at' => now()->toIso8601String(),
                'admin_uuid' => $admin->uuid,
                'admin_notes' => $notes,
            ]),
        ]);

        $this->auditLogService->record(
            AuditAction::PrivacyErasureRejected,
            $admin,
            $erasureRequest->fresh(),
            [
                'admin_uuid' => $admin->uuid,
                'erasure_request_id' => $erasureRequest->id,
                'notes' => $notes,
            ],
            $request,
        );

        return ['erasure_request' => $erasureRequest->fresh()];
    }

    /**
     * @return array{erasure_request: DataErasureRequest}
     */
    private function markErasureRequestReviewed(
        DataErasureRequest $erasureRequest,
        User $admin,
        ?string $notes,
        ?Request $request,
    ): array {
        if (! in_array($erasureRequest->status, [DataErasureRequestStatus::Pending, DataErasureRequestStatus::Processing], true)) {
            throw new ApiException(
                'ERASURE_NOT_REVIEWABLE',
                'La richiesta non è più in coda di revisione.',
                422,
            );
        }

        $erasureRequest->update([
            'metadata' => array_merge($erasureRequest->metadata ?? [], [
                'admin_reviewed_at' => now()->toIso8601String(),
                'admin_uuid' => $admin->uuid,
                'admin_notes' => $notes,
            ]),
        ]);

        $this->auditLogService->record(
            AuditAction::PrivacyErasureReviewed,
            $admin,
            $erasureRequest->fresh(),
            [
                'admin_uuid' => $admin->uuid,
                'erasure_request_id' => $erasureRequest->id,
                'notes' => $notes,
            ],
            $request,
        );

        return ['erasure_request' => $erasureRequest->fresh()];
    }

    public function processErasureRequest(int $erasureRequestId): void
    {
        $request = DataErasureRequest::query()->find($erasureRequestId);

        if ($request === null || $request->status !== DataErasureRequestStatus::Pending) {
            return;
        }

        DB::transaction(function () use ($request): void {
            $request->update([
                'status' => DataErasureRequestStatus::Processing,
            ]);

            $user = User::query()->find($request->user_id);

            if ($user === null) {
                $request->update([
                    'status' => DataErasureRequestStatus::Completed,
                    'processed_at' => now(),
                    'metadata' => array_merge($request->metadata ?? [], [
                        'skipped' => 'user_not_found',
                    ]),
                ]);

                return;
            }

            $leadsAnonymized = $this->leadAnonymizationService->anonymizeOwnedByUser($user);

            $this->notifyPartnersOfErasure($user);

            $user->tokens()->delete();

            $this->anonymizeAndSoftDeleteUser($user);

            $request->update([
                'status' => DataErasureRequestStatus::Completed,
                'processed_at' => now(),
                'metadata' => array_merge($request->metadata ?? [], [
                    'leads_anonymized' => $leadsAnonymized,
                    'tokens_revoked' => true,
                ]),
            ]);
        });
    }

    private function notifyPrivacyContactOfErasureRequest(DataErasureRequest $erasureRequest, User $user): void
    {
        $recipient = config('wenando.privacy_contact_email');

        if (! is_string($recipient) || $recipient === '') {
            Log::info('GDPR erasure request submitted (no privacy contact email configured)', [
                'erasure_request_id' => $erasureRequest->id,
                'user_id' => $user->id,
            ]);

            return;
        }

        Mail::to($recipient)->queue(new PrivacyErasureRequestMail($erasureRequest, $user));
    }

    private function notifyPartnersOfErasure(User $user): void
    {
        $companyIds = LeadMatch::query()
            ->whereNotNull('unlocked_at')
            ->whereHas('lead', static function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->distinct()
            ->pluck('company_id')
            ->values()
            ->all();

        Log::info('GDPR erasure: partner cessation notice stub', [
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'company_ids' => $companyIds,
            'message' => 'Partners with unlocked leads should cease processing personal data for this subject.',
        ]);
    }

    private function anonymizeAndSoftDeleteUser(User $user): void
    {
        $user->forceFill([
            'email' => 'deleted_'.$user->id.'@'.self::ANONYMIZED_USER_EMAIL_DOMAIN,
            'name' => '',
            'phone' => null,
            'password' => null,
        ])->save();

        $user->delete();
    }
}
