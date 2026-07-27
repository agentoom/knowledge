<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HighLatencyNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $query,
        public readonly int $latencyMs,
        public readonly int $providersQueried,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('[Agentoom Alert] High search latency: %d ms', $this->latencyMs),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.notifications.high-latency',
            with: [
                'query' => $this->query,
                'latencyMs' => $this->latencyMs,
                'providersQueried' => $this->providersQueried,
                'timestamp' => now()->toIso8601String(),
            ],
        );
    }
}
