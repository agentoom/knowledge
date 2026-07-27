<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\Facades\Settings;
use Illuminate\View\View;
use Livewire\Component;

class Notifications extends Component
{
    public bool $emailNotificationsEnabled = false;

    public string $notificationEmail = '';

    public bool $webhookEnabled = false;

    public string $webhookUrl = '';

    public bool $indexingCompletedNotify = true;

    public bool $indexingFailedNotify = true;

    public int $latencyThresholdMs = 5000;

    public int $syncFailureThreshold = 3;

    public int $cooldownSeconds = 300;

    public bool $searchLatencyAlerts = true;

    public bool $syncFailureAlerts = true;

    public bool $federationFailureAlerts = true;

    public function mount(): void
    {
        $settings = Settings::getMany([
            'notifications.email_enabled',
            'notifications.email_address',
            'notifications.webhook_enabled',
            'notifications.webhook_url',
            'notifications.indexing_completed',
            'notifications.indexing_failed',
            'notifications.latency_threshold_ms',
            'notifications.sync_failure_threshold',
            'notifications.cooldown_seconds',
            'notifications.search_latency_enabled',
            'notifications.sync_failure_enabled',
            'notifications.federation_failure_enabled',
        ], [
            'notifications.email_enabled' => false,
            'notifications.email_address' => '',
            'notifications.webhook_enabled' => false,
            'notifications.webhook_url' => '',
            'notifications.indexing_completed' => true,
            'notifications.indexing_failed' => true,
            'notifications.latency_threshold_ms' => 5000,
            'notifications.sync_failure_threshold' => 3,
            'notifications.cooldown_seconds' => 300,
            'notifications.search_latency_enabled' => true,
            'notifications.sync_failure_enabled' => true,
            'notifications.federation_failure_enabled' => true,
        ]);

        $this->emailNotificationsEnabled = (bool) $settings['notifications.email_enabled'];
        $this->notificationEmail = $settings['notifications.email_address'];
        $this->webhookEnabled = (bool) $settings['notifications.webhook_enabled'];
        $this->webhookUrl = $settings['notifications.webhook_url'];
        $this->indexingCompletedNotify = (bool) $settings['notifications.indexing_completed'];
        $this->indexingFailedNotify = (bool) $settings['notifications.indexing_failed'];
        $this->latencyThresholdMs = (int) $settings['notifications.latency_threshold_ms'];
        $this->syncFailureThreshold = (int) $settings['notifications.sync_failure_threshold'];
        $this->cooldownSeconds = (int) $settings['notifications.cooldown_seconds'];
        $this->searchLatencyAlerts = (bool) $settings['notifications.search_latency_enabled'];
        $this->syncFailureAlerts = (bool) $settings['notifications.sync_failure_enabled'];
        $this->federationFailureAlerts = (bool) $settings['notifications.federation_failure_enabled'];
    }

    public function save(): void
    {
        $this->validate([
            'notificationEmail' => ['nullable', 'string', 'max:1024'],
            'webhookUrl' => ['nullable', 'url', 'max:1024'],
            'latencyThresholdMs' => ['required', 'integer', 'min:100', 'max:60000'],
            'syncFailureThreshold' => ['required', 'integer', 'min:1', 'max:100'],
            'cooldownSeconds' => ['required', 'integer', 'min:30', 'max:3600'],
        ]);

        Settings::set('notifications.email_enabled', $this->emailNotificationsEnabled, 'boolean');
        Settings::set('notifications.email_address', $this->notificationEmail, 'string');
        Settings::set('notifications.webhook_enabled', $this->webhookEnabled, 'boolean');
        Settings::set('notifications.webhook_url', $this->webhookUrl, 'string');
        Settings::set('notifications.indexing_completed', $this->indexingCompletedNotify, 'boolean');
        Settings::set('notifications.indexing_failed', $this->indexingFailedNotify, 'boolean');
        Settings::set('notifications.latency_threshold_ms', $this->latencyThresholdMs, 'integer');
        Settings::set('notifications.sync_failure_threshold', $this->syncFailureThreshold, 'integer');
        Settings::set('notifications.cooldown_seconds', $this->cooldownSeconds, 'integer');
        Settings::set('notifications.search_latency_enabled', $this->searchLatencyAlerts, 'boolean');
        Settings::set('notifications.sync_failure_enabled', $this->syncFailureAlerts, 'boolean');
        Settings::set('notifications.federation_failure_enabled', $this->federationFailureAlerts, 'boolean');

        $this->dispatch('notify', message: 'Notification settings saved successfully.');
        $this->dispatch('settings-clean');
    }

    public function render(): View
    {
        return view('livewire.admin.settings.notifications');
    }
}
