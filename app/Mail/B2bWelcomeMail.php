<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class B2bWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $recipientName,
    ) {
        $this->onConnection('database');
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        $replyTo = config('mail.reply_to');

        return new Envelope(
            subject: 'Benvenuto su Wenando Pro',
            replyTo: [
                new Address($replyTo['address'], $replyTo['name']),
            ],
        );
    }

    public function content(): Content
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return new Content(
            text: 'mail.b2b-welcome-text',
            html: 'mail.b2b-welcome',
            with: [
                'recipientName' => $this->recipientName,
                'onboardingUrl' => $frontendUrl.'/pro/onboarding',
            ],
        );
    }
}
