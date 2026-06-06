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

class EditorialReviewOutcomeMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $contentTitle,
        public readonly bool $approved,
        public readonly ?string $note,
        public readonly string $contentUrl,
    ) {
        $this->onConnection('database');
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        $replyTo = config('mail.reply_to');

        return new Envelope(
            subject: 'Esito revisione editoriale',
            replyTo: [
                new Address($replyTo['address'], $replyTo['name']),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.editorial-review-outcome-text',
            html: 'mail.editorial-review-outcome',
            with: [
                'recipientName' => $this->recipientName,
                'contentTitle' => $this->contentTitle,
                'approved' => $this->approved,
                'note' => $this->note,
                'contentUrl' => $this->contentUrl,
            ],
        );
    }
}
