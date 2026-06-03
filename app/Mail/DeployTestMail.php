<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Synchronous SMTP probe for `wenando:mail-test` (not queued).
 */
class DeployTestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Wenando SMTP test — '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.test',
            with: [
                'sentAt' => now()->toIso8601String(),
                'appUrl' => config('app.url'),
            ],
        );
    }
}
