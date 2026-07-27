<?php

namespace App\Listeners;

use App\Notifications\SyncFailureNotification;
use App\Services\NotificationService;
use App\Settings\Facades\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotifySyncFailure
{
    /**
     * Handle a sync failure notification.
     *
     * @param  array<string, mixed>  $context
     */
    public function handle(string $sourceName, string $failureType, string $errorMessage, NotificationService $notifications): void
    {
        $threshold = (int) Settings::get(
            'notifications.sync_failure_threshold',
            (int) env('KNOWLEDGE_SYNC_FAILURE_THRESHOLD', 3),
        );

        $cacheKey = "sync_failure_count:{$failureType}:{$sourceName}";
        $consecutiveFailures = (int) Cache::get($cacheKey, 0) + 1;

        // Store the failure count for 24 hours — it resets on success via the caller
        Cache::put($cacheKey, $consecutiveFailures, now()->addDay());

        if ($consecutiveFailures < $threshold) {
            Log::info('NotifySyncFailure: failure below threshold, no alert sent.', [
                'source' => $sourceName,
                'type' => $failureType,
                'consecutive' => $consecutiveFailures,
                'threshold' => $threshold,
            ]);

            return;
        }

        $notifications->send(
            alertType: 'sync_failure',
            mailable: new SyncFailureNotification(
                sourceName: $sourceName,
                failureType: $failureType,
                errorMessage: $errorMessage,
                consecutiveFailures: $consecutiveFailures,
            ),
            webhookPayload: [
                'text' => sprintf('Agentoom Alert: Sync failure on "%s" (%d consecutive)', $sourceName, $consecutiveFailures),
                'attachments' => [
                    [
                        'title' => 'Sync Failure',
                        'fields' => [
                            ['title' => 'Source', 'value' => $sourceName],
                            ['title' => 'Type', 'value' => $failureType],
                            ['title' => 'Error', 'value' => $errorMessage],
                            ['title' => 'Consecutive failures', 'value' => (string) $consecutiveFailures],
                        ],
                    ],
                ],
            ],
        );
    }

    /**
     * Reset the consecutive failure counter for a source (called on successful sync).
     */
    public static function resetFailureCount(string $sourceName, string $failureType): void
    {
        Cache::forget("sync_failure_count:{$failureType}:{$sourceName}");
    }
}
