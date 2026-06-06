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

class EditorialSeoReviewMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $contentTitle,
        public readonly int $seoScore,
        public readonly int $minScore,
        public readonly string $reason,
        public readonly string $editUrl,
    ) {
        $this->onConnection('database');
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        $replyTo = config('mail.reply_to');

        return new Envelope(
            subject: 'SEO da rivedere',
            replyTo: [
                new Address($replyTo['address'], $replyTo['name']),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.editorial-seo-review-text',
            html: 'mail.editorial-seo-review',
            with: [
                'recipientName' => $this->recipientName,
                'contentTitle' => $this->contentTitle,
                'seoScore' => $this->seoScore,
                'minScore' => $this->minScore,
                'reason' => $this->reason,
                'editUrl' => $this->editUrl,
            ],
        );
    }
}
