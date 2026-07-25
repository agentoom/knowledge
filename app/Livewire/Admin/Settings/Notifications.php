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

    public function mount(): void
    {
        $settings = Settings::getMany([
            'notifications.email_enabled',
            'notifications.email_address',
            'notifications.webhook_enabled',
            'notifications.webhook_url',
            'notifications.indexing_completed',
            'notifications.indexing_failed',
        ], [
            'notifications.email_enabled' => false,
            'notifications.email_address' => '',
            'notifications.webhook_enabled' => false,
            'notifications.webhook_url' => '',
            'notifications.indexing_completed' => true,
            'notifications.indexing_failed' => true,
        ]);

        $this->emailNotificationsEnabled = (bool) $settings['notifications.email_enabled'];
        $this->notificationEmail = $settings['notifications.email_address'];
        $this->webhookEnabled = (bool) $settings['notifications.webhook_enabled'];
        $this->webhookUrl = $settings['notifications.webhook_url'];
        $this->indexingCompletedNotify = (bool) $settings['notifications.indexing_completed'];
        $this->indexingFailedNotify = (bool) $settings['notifications.indexing_failed'];
    }

    public function save(): void
    {
        $this->validate([
            'notificationEmail' => ['nullable', 'email', 'max:255'],
            'webhookUrl' => ['nullable', 'url', 'max:1024'],
        ]);

        Settings::set('notifications.email_enabled', $this->emailNotificationsEnabled, 'boolean');
        Settings::set('notifications.email_address', $this->notificationEmail, 'string');
        Settings::set('notifications.webhook_enabled', $this->webhookEnabled, 'boolean');
        Settings::set('notifications.webhook_url', $this->webhookUrl, 'string');
        Settings::set('notifications.indexing_completed', $this->indexingCompletedNotify, 'boolean');
        Settings::set('notifications.indexing_failed', $this->indexingFailedNotify, 'boolean');

        $this->dispatch('notify', message: 'Notification settings saved successfully.');
        $this->dispatch('settings-clean');
    }

    public function render(): View
    {
        return view('livewire.admin.settings.notifications');
    }
}
