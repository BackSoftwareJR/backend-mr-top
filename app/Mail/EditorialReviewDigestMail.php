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

class EditorialReviewDigestMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly int $pendingModeration,
        public readonly int $seoAttention,
        public readonly string $reviewUrl,
    ) {
        $this->onConnection('database');
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        $replyTo = config('mail.reply_to');

        return new Envelope(
            subject: 'Riepilogo revisioni editoriali',
            replyTo: [
                new Address($replyTo['address'], $replyTo['name']),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.editorial-review-digest-text',
            html: 'mail.editorial-review-digest',
            with: [
                'recipientName' => $this->recipientName,
                'pendingModeration' => $this->pendingModeration,
                'seoAttention' => $this->seoAttention,
                'reviewUrl' => $this->reviewUrl,
            ],
        );
    }
}
