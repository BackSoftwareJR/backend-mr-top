<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * OTP verification email — sent synchronously from OtpAuthService (critical path).
 */
class OtpMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresInMinutes = 10,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = config('mail.reply_to');

        return new Envelope(
            subject: 'Il tuo codice di verifica Wenando',
            replyTo: [
                new Address($replyTo['address'], $replyTo['name']),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.otp-text',
            html: 'mail.otp',
            with: [
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }
}
