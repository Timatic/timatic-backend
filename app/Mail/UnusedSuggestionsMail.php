<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UnusedSuggestionsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        protected readonly string $content,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Unused Suggestions',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reminders.mail-layout',
            with: [
                'content' => $this->content,
            ],
        );
    }
}
