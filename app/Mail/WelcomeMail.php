<?php

namespace App\Mail;

use App\Mail\Concerns\QueuesOnDatabase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Post-registration welcome email (stub — wire from onboarding when implemented).
 */
class WelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use QueuesOnDatabase;
    use SerializesModels;

    public function __construct(
        public readonly string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = config('mail.reply_to');

        return new Envelope(
            subject: 'Benvenuto su Wenando',
            replyTo: [
                new Address($replyTo['address'], $replyTo['name']),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.welcome-text',
            html: 'mail.welcome',
            with: [
                'recipientName' => $this->recipientName,
                'frontendUrl' => env('FRONTEND_URL', 'https://wenando.com'),
            ],
        );
    }
}
