<?php

declare(strict_types=1);

namespace App\Mail;

use App\DataTransferObjects\UnmappedRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class UnmappedRepositoriesDigest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param Collection<int, UnmappedRepository> $repositories */
    public function __construct(
        public readonly Collection $repositories,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: trans_choice('integrations.unmapped_repositories_digest.subject', $this->repositories->count()),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.repositories.unmapped-digest',
            with: [
                'repositoriesByIntegration' => $this->repositories->groupBy('integrationTitle'),
            ],
        );
    }
}
