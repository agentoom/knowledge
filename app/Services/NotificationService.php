<?php

namespace App\Services;

use App\Settings\Facades\Settings;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Send an email notification if email notifications are enabled and configured.
     *
     * @param  Mailable  $mailable
     */
    public function sendEmail($mailable): void
    {
        $enabled = (bool) Settings::get('notifications.email_enabled', false);
        $addresses = Settings::get('notifications.email_address', '');

        if (! $enabled || $addresses === '' || $addresses === null) {
            return;
        }

        $recipients = array_filter(
            array_map('trim', explode(',', (string) $addresses)),
            fn (string $email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL),
        );

        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send($mailable);
        } catch (\Throwable $e) {
            Log::warning('NotificationService: failed to send email notification.', [
                'error' => $e->getMessage(),
                'recipients' => $recipients,
            ]);
        }
    }

    /**
     * Send a webhook notification if webhook notifications are enabled and configured.
     *
     * @param  array<string, mixed>  $payload
     */
    public function sendWebhook(array $payload): void
    {
        $enabled = (bool) Settings::get('notifications.webhook_enabled', false);
        $url = Settings::get('notifications.webhook_url', '');

        if (! $enabled || $url === '' || $url === null) {
            return;
        }

        try {
            Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('NotificationService: failed to send webhook notification.', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);
        }
    }

    /**
     * Send both email and webhook notifications, respecting per-alert-type toggles
     * and cooldown windows to prevent notification floods.
     *
     * @param  Mailable  $mailable
     * @param  array<string, mixed>  $webhookPayload
     */
    public function send(string $alertType, $mailable, array $webhookPayload): void
    {
        // Check if this alert type is enabled
        $alertEnabledKey = match ($alertType) {
            'search_latency' => 'notifications.search_latency_enabled',
            'sync_failure' => 'notifications.sync_failure_enabled',
            'federation_failure' => 'notifications.federation_failure_enabled',
            default => null,
        };

        if ($alertEnabledKey !== null && ! (bool) Settings::get($alertEnabledKey, true)) {
            return;
        }

        // Cooldown — prevent duplicate alerts within the cooldown window
        $cooldownKey = "notification_cooldown:{$alertType}";
        $cooldownSeconds = (int) Settings::get('notifications.cooldown_seconds', 300);

        if (Cache::has($cooldownKey)) {
            return;
        }

        Cache::put($cooldownKey, true, $cooldownSeconds);

        $this->sendEmail($mailable);
        $this->sendWebhook($webhookPayload);
    }
}
