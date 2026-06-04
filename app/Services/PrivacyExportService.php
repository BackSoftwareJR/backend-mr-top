<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AppointmentType;
use App\Enums\AuditAction;
use App\Mail\PrivacyExportNotificationMail;
use App\Models\Appointment;
use App\Models\ConsentLog;
use App\Models\Lead;
use App\Models\SavedMatch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class PrivacyExportService
{
    private const EXPORT_FORMAT_VERSION = '1.0';

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function exportForUser(User $user, ?Request $request = null): array
    {
        $payload = $this->buildExport($user);

        $this->auditLogService->record(
            AuditAction::PrivacyExport,
            $user,
            $user,
            ['format_version' => self::EXPORT_FORMAT_VERSION],
            $request,
        );

        if (config('wenando.privacy_export_notify')) {
            $recipient = config('wenando.privacy_contact_email');

            if (is_string($recipient) && $recipient !== '') {
                Mail::to($recipient)->queue(new PrivacyExportNotificationMail($user));
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildExport(User $user): array
    {
        return [
            'exported_at' => Carbon::now()->toIso8601String(),
            'format' => 'json',
            'format_version' => self::EXPORT_FORMAT_VERSION,
            'profile' => $this->exportProfile($user),
            'consents' => $this->exportConsents($user),
            'leads' => $this->exportLeads($user),
            'saved_matches' => $this->exportSavedMatches($user),
            'advisor_bookings' => $this->exportAdvisorBookings($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exportProfile(User $user): array
    {
        return [
            'uuid' => $user->uuid,
            'email' => $user->email,
            'name' => $user->name,
            'phone' => $user->phone,
            'created_at' => $user->created_at?->toIso8601String(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportConsents(User $user): array
    {
        return ConsentLog::query()
            ->forUser($user->id)
            ->latestFirst()
            ->get()
            ->map(static function (ConsentLog $log): array {
                $row = [
                    'consent_type' => $log->consent_type->value,
                    'policy_version' => $log->policy_version,
                    'consent_given' => $log->consent_given,
                    'consent_text_hash' => $log->consent_text_hash,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];

                if ($log->ip_address !== null) {
                    $row['ip_address'] = $log->ip_address;
                }

                if ($log->user_agent !== null) {
                    $row['user_agent'] = $log->user_agent;
                }

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportLeads(User $user): array
    {
        return Lead::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id);

                if ($user->email !== null && $user->email !== '') {
                    $query->orWhere('contact_email', $user->email);
                }
            })
            ->orderBy('created_at')
            ->get()
            ->map(fn (Lead $lead): array => $this->exportLead($lead))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function exportLead(Lead $lead): array
    {
        return [
            'public_ref' => $lead->public_ref,
            'uuid' => $lead->uuid,
            'status' => $lead->status->value,
            'contact_name' => $lead->contact_name,
            'contact_phone' => $lead->contact_phone,
            'contact_email' => $lead->contact_email,
            'location_label' => $lead->location_label,
            'budget_min' => $lead->budget_min,
            'budget_max' => $lead->budget_max,
            'need_summary' => $lead->need_summary,
            'title' => $lead->title,
            'payload' => $lead->payload,
            'created_at' => $lead->created_at?->toIso8601String(),
            'updated_at' => $lead->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportSavedMatches(User $user): array
    {
        return SavedMatch::query()
            ->where('user_id', $user->id)
            ->with(['company:id,uuid,organization_name', 'leadMatch:id,match_score,rank'])
            ->orderBy('created_at')
            ->get()
            ->map(static function (SavedMatch $saved): array {
                return [
                    'company_uuid' => $saved->company?->uuid,
                    'company_name' => $saved->company?->organization_name,
                    'match_score' => $saved->leadMatch?->match_score,
                    'saved_at' => $saved->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportAdvisorBookings(User $user): array
    {
        return Appointment::query()
            ->where('user_id', $user->id)
            ->where('type', AppointmentType::Advisor)
            ->orderBy('scheduled_date')
            ->get()
            ->map(static function (Appointment $appointment): array {
                return [
                    'scheduled_date' => $appointment->scheduled_date?->toDateString(),
                    'scheduled_time' => $appointment->scheduled_time,
                    'client_name' => $appointment->client_name,
                    'note' => $appointment->note,
                    'created_at' => $appointment->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }
}
