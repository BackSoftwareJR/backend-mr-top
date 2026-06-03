<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConsentType;
use App\Models\ConsentLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ConsentLogService
{
    /**
     * Record privacy (and optional marketing) consent at B2C lead submission.
     *
     * @param  array<string, mixed>  $validated
     * @return list<ConsentLog>
     */
    public function recordLeadSubmissionConsents(
        Lead $lead,
        Request $request,
        ?User $user,
        array $validated,
    ): array {
        $policyVersion = $validated['policy_version']
            ?? config('wenando.privacy_policy_version', '1.0.0');
        $sessionId = $this->resolveSessionId($request, $validated['session_id'] ?? null);

        $consents = [[
            'consent_type' => ConsentType::PrivacyPolicy->value,
            'policy_version' => $policyVersion,
            'consent_given' => true,
            'consent_text_hash' => $validated['consent_text_hash'],
            'session_id' => $sessionId,
            'lead_uuid' => $lead->uuid,
            'metadata' => [
                'source' => 'b2c_lead_submission',
                'sector_slug' => $validated['sector_slug'],
            ],
        ]];

        if (($validated['consent']['marketing_accepted'] ?? false) === true) {
            $consents[] = [
                'consent_type' => ConsentType::Marketing->value,
                'policy_version' => $policyVersion,
                'consent_given' => true,
                'consent_text_hash' => $validated['consent_text_hash'],
                'session_id' => $sessionId,
                'lead_uuid' => $lead->uuid,
                'metadata' => ['source' => 'b2c_lead_submission'],
            ];
        }

        return $this->recordConsents($consents, $request, $user);
    }

    /**
     * @param  list<array{
     *     consent_type: string,
     *     policy_version: string,
     *     consent_given: bool,
     *     consent_text_hash: string,
     *     session_id?: string|null,
     *     lead_uuid?: string|null,
     *     metadata?: array<string, mixed>|null
     * }>  $consents
     * @return list<ConsentLog>
     */
    public function recordConsents(array $consents, Request $request, ?User $user = null): array
    {
        $ipAddress = $request->ip();
        $userAgent = $this->truncateUserAgent($request->userAgent());
        $recorded = [];

        foreach ($consents as $consent) {
            $log = ConsentLog::query()->create([
                'user_id' => $user?->id,
                'lead_id' => $this->resolveLeadId($consent),
                'session_id' => $consent['session_id'] ?? $this->resolveSessionId($request),
                'consent_type' => $consent['consent_type'],
                'policy_version' => $consent['policy_version'],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'consent_given' => $consent['consent_given'],
                'consent_text_hash' => $consent['consent_text_hash'],
                'metadata' => $consent['metadata'] ?? null,
            ]);

            $recorded[] = $log;
        }

        return $recorded;
    }

    public function hasValidConsent(
        ConsentType|string $type,
        ?int $userId = null,
        ?string $sessionId = null,
        ?string $policyVersion = null,
    ): bool {
        if ($userId === null && ($sessionId === null || $sessionId === '')) {
            return false;
        }

        $query = ConsentLog::query()
            ->ofType($type)
            ->given()
            ->latestFirst();

        if ($userId !== null) {
            $query->forUser($userId);
        } else {
            $query->forSession($sessionId);
        }

        if ($policyVersion !== null) {
            $query->where('policy_version', $policyVersion);
        }

        return $query->exists();
    }

    /**
     * @return Collection<string, ConsentLog>
     */
    public function latestByTypeForUser(int $userId): Collection
    {
        return ConsentLog::query()
            ->forUser($userId)
            ->latestFirst()
            ->get()
            ->unique(fn (ConsentLog $log) => $log->consent_type->value)
            ->keyBy(fn (ConsentLog $log) => $log->consent_type->value);
    }

    /**
     * @param  array<string, mixed>  $consent
     */
    private function resolveLeadId(array $consent): ?int
    {
        if (empty($consent['lead_uuid'])) {
            return null;
        }

        return Lead::query()
            ->where('uuid', $consent['lead_uuid'])
            ->value('id');
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return mb_strlen($userAgent) > 512
            ? mb_substr($userAgent, 0, 512)
            : $userAgent;
    }

    private function resolveSessionId(Request $request, ?string $provided = null): ?string
    {
        if ($provided !== null && $provided !== '') {
            return $provided;
        }

        if ($request->hasSession()) {
            return $request->session()->getId();
        }

        return null;
    }
}
