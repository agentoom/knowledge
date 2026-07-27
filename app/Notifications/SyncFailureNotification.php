<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SyncFailureNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $sourceName,
        public readonly string $failureType,
        public readonly string $errorMessage,
        public readonly int $consecutiveFailures,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '[Agentoom Alert] Sync failure: %s (%d consecutive)',
                $this->sourceName,
                $this->consecutiveFailures,
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notifications.sync-failure',
            with: [
                'sourceName' => $this->sourceName,
                'failureType' => $this->failureType,
                'errorMessage' => $this->errorMessage,
                'consecutiveFailures' => $this->consecutiveFailures,
                'timestamp' => now()->toIso8601String(),
            ],
        );
    }
}
