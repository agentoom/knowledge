<?php

namespace App\Listeners;

use App\Events\RetrievalExecuted;
use App\Notifications\HighLatencyNotification;
use App\Services\NotificationService;
use App\Settings\Facades\Settings;

class CheckSearchLatency
{
    public function handle(RetrievalExecuted $event): void
    {
        $notifications = app(NotificationService::class);

        $thresholdMs = (int) Settings::get(
            'notifications.latency_threshold_ms',
            (int) env('MCP_SEARCH_LATENCY_THRESHOLD_MS', 5000),
        );

        if ($event->durationMs < $thresholdMs) {
            return;
        }

        $notifications->send(
            alertType: 'search_latency',
            mailable: new HighLatencyNotification(
                query: $event->query,
                latencyMs: (int) $event->durationMs,
                providersQueried: $event->providersQueried,
            ),
            webhookPayload: [
                'text' => 'Agentoom Alert: High search latency detected',
                'attachments' => [
                    [
                        'title' => 'Search Performance',
                        'fields' => [
                            ['title' => 'Query', 'value' => $event->query],
                            ['title' => 'Latency', 'value' => sprintf('%d ms', (int) $event->durationMs)],
                            ['title' => 'Providers', 'value' => (string) $event->providersQueried],
                        ],
                    ],
                ],
            ],
        );
    }
}
