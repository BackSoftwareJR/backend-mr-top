<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AppointmentType;
use App\Mail\AdvisorBookingCancellationMail;
use App\Mail\AdvisorBookingConfirmationMail;
use App\Mail\AdvisorBookingRescheduleMail;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class AdvisorBookingService
{
    public function __construct(
        private readonly AdvisorProfileService $advisorProfileService,
    ) {}

    /**
     * @param  array{lead_uuid?: string|null, name: string, scheduled_date: string, scheduled_time: string}  $data
     */
    public function create(User $user, array $data): Appointment
    {
        $lead = null;

        if (! empty($data['lead_uuid'])) {
            $lead = $this->resolveLeadForUser($user, $data['lead_uuid']);
        }

        $appointment = Appointment::query()->create([
            'user_id' => $user->id,
            'lead_id' => $lead?->id,
            'client_name' => $data['name'],
            'scheduled_date' => $data['scheduled_date'],
            'scheduled_time' => $data['scheduled_time'],
            'type' => AppointmentType::Advisor,
        ]);

        $appointment->load('lead:id,title,contact_email');

        $this->queueConfirmationMail($user, $appointment);

        return $appointment;
    }

    public function cancel(Appointment $appointment): void
    {
        $appointment->load(['user', 'lead:id,title,contact_email']);

        $this->queueCancellationMail($appointment);

        $appointment->delete();
    }

    /**
     * @param  array{scheduled_date: string, scheduled_time: string}  $data
     */
    public function reschedule(Appointment $appointment, array $data): Appointment
    {
        $appointment->update([
            'scheduled_date' => $data['scheduled_date'],
            'scheduled_time' => $data['scheduled_time'],
        ]);

        $appointment->load(['user', 'lead:id,title,contact_email']);

        $this->queueRescheduleMail($appointment);

        return $appointment->fresh(['lead:id,title']);
    }

    /**
     * @return array{upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>}
     */
    public function listForUser(User $user): array
    {
        $bookings = Appointment::query()
            ->where('user_id', $user->id)
            ->where('type', AppointmentType::Advisor)
            ->with('lead:id,title')
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();

        $now = now();
        $upcoming = [];
        $past = [];

        foreach ($bookings as $appointment) {
            $item = $this->formatBooking($appointment);
            $scheduledAt = $this->scheduledAt($appointment);

            if ($scheduledAt !== null && $scheduledAt->gte($now)) {
                $upcoming[] = $item;
            } else {
                $past[] = $item;
            }
        }

        return [
            'upcoming' => $upcoming,
            'past' => array_reverse($past),
        ];
    }

    /**
     * @return array{upcoming: list<array<string, mixed>>, past: list<array<string, mixed>>}
     */
    public function listForAdmin(): array
    {
        $bookings = Appointment::query()
            ->where('type', AppointmentType::Advisor)
            ->with(['user:id,name,email', 'lead:id,title'])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();

        $now = now();
        $upcoming = [];
        $past = [];

        foreach ($bookings as $appointment) {
            $item = $this->formatAdminBooking($appointment);
            $scheduledAt = $this->scheduledAt($appointment);

            if ($scheduledAt !== null && $scheduledAt->gte($now)) {
                $upcoming[] = $item;
            } else {
                $past[] = $item;
            }
        }

        return [
            'upcoming' => $upcoming,
            'past' => array_reverse($past),
        ];
    }

    private function queueConfirmationMail(User $user, Appointment $appointment): void
    {
        $recipient = $this->resolveRecipient($user->email, $appointment->lead?->contact_email);

        if ($recipient === null) {
            return;
        }

        $advisorName = $this->advisorProfileService->defaultPayload()['name'] ?? 'Marco';

        $this->queueAdvisorMailWithOpsBcc(
            $recipient,
            new AdvisorBookingConfirmationMail($appointment, $advisorName),
        );
    }

    private function queueCancellationMail(Appointment $appointment): void
    {
        $recipient = $this->resolveRecipient($appointment->user?->email, $appointment->lead?->contact_email);

        if ($recipient === null) {
            return;
        }

        $advisorName = $this->advisorProfileService->defaultPayload()['name'] ?? 'Marco';

        $this->queueAdvisorMailWithOpsBcc(
            $recipient,
            new AdvisorBookingCancellationMail($appointment, $advisorName),
        );
    }

    private function queueRescheduleMail(Appointment $appointment): void
    {
        $recipient = $this->resolveRecipient($appointment->user?->email, $appointment->lead?->contact_email);

        if ($recipient === null) {
            return;
        }

        $advisorName = $this->advisorProfileService->defaultPayload()['name'] ?? 'Marco';

        $this->queueAdvisorMailWithOpsBcc(
            $recipient,
            new AdvisorBookingRescheduleMail($appointment, $advisorName),
        );
    }

    private function queueAdvisorMailWithOpsBcc(string $recipient, Mailable $mailable): void
    {
        $pending = Mail::to($recipient);

        $bcc = config('wenando.privacy_contact_email');

        if (is_string($bcc) && $bcc !== '' && filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
            $pending->bcc($bcc);
        }

        $pending->queue($mailable);
    }

    private function resolveRecipient(?string $userEmail, ?string $leadContactEmail): ?string
    {
        $recipient = $userEmail ?: $leadContactEmail;

        if (! is_string($recipient) || $recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $recipient;
    }

    private function resolveLeadForUser(User $user, string $leadUuid): Lead
    {
        $lead = Lead::query()->where('uuid', $leadUuid)->firstOrFail();

        if ($lead->user_id === null) {
            $lead->update(['user_id' => $user->id]);
        } elseif ($lead->user_id !== $user->id) {
            abort(403, 'Lead non associabile.');
        }

        return $lead->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBooking(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'scheduled_date' => $appointment->scheduled_date?->toDateString(),
            'scheduled_time' => substr((string) $appointment->scheduled_time, 0, 5),
            'name' => $appointment->client_name,
            'lead_title' => $appointment->lead?->title,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAdminBooking(Appointment $appointment): array
    {
        $scheduledAt = $this->scheduledAt($appointment);

        return [
            'id' => $appointment->id,
            'consumer_name' => $appointment->client_name,
            'consumer_email' => $appointment->user?->email,
            'lead_title' => $appointment->lead?->title,
            'scheduled_at' => $scheduledAt?->toIso8601String(),
            'scheduled_date' => $appointment->scheduled_date?->toDateString(),
            'scheduled_time' => substr((string) $appointment->scheduled_time, 0, 5),
        ];
    }

    private function scheduledAt(Appointment $appointment): ?Carbon
    {
        if ($appointment->scheduled_date === null || $appointment->scheduled_time === null) {
            return null;
        }

        $time = substr((string) $appointment->scheduled_time, 0, 5);

        return Carbon::parse($appointment->scheduled_date->toDateString().' '.$time);
    }
}
