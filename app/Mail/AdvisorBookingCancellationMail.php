<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class AdvisorBookingCancellationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $advisorName,
    ) {
        $this->onConnection('database');
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        $replyTo = config('mail.reply_to');

        return new Envelope(
            subject: 'Cancellazione prenotazione consulenza Wenando',
            replyTo: [
                new Address($replyTo['address'], $replyTo['name']),
            ],
        );
    }

    public function content(): Content
    {
        $scheduledDate = $this->appointment->scheduled_date;
        $dateLabel = $scheduledDate instanceof Carbon
            ? $scheduledDate->locale('it')->isoFormat('dddd D MMMM YYYY')
            : (string) $scheduledDate;

        $time = substr((string) $this->appointment->scheduled_time, 0, 5);
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return new Content(
            text: 'mail.advisor-booking-cancellation-text',
            html: 'mail.advisor-booking-cancellation',
            with: [
                'clientName' => $this->appointment->client_name,
                'advisorName' => $this->advisorName,
                'scheduledDate' => $dateLabel,
                'scheduledTime' => $time,
                'leadTitle' => $this->appointment->lead?->title,
                'helpUrl' => $frontendUrl.'/area-personale/aiuto',
                'adminBookingsUrl' => $frontendUrl.'/admin/advisor-bookings',
            ],
        );
    }
}
