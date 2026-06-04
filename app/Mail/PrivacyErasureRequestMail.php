<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\DataErasureRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrivacyErasureRequestMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly DataErasureRequest $erasureRequest,
        public readonly User $user,
    ) {
        $this->onConnection('database');
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Richiesta cancellazione dati (GDPR Art. 17)',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.privacy-erasure-request-text',
            with: [
                'erasureRequestId' => $this->erasureRequest->id,
                'userUuid' => $this->user->uuid,
                'userEmail' => $this->user->email,
                'reason' => $this->erasureRequest->reason,
                'requestedAt' => $this->erasureRequest->requested_at?->toIso8601String(),
            ],
        );
    }
}
