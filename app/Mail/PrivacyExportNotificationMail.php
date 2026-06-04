<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrivacyExportNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
    ) {
        $this->onConnection('database');
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Export dati personali (GDPR Art. 15)',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.privacy-export-notification-text',
            with: [
                'userUuid' => $this->user->uuid,
                'userEmail' => $this->user->email,
                'exportedAt' => now()->toIso8601String(),
            ],
        );
    }
}
