<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\Facades\Settings;
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
        $this->emailNotificationsEnabled = Settings::get('notifications.email_enabled', false);
        $this->notificationEmail = Settings::get('notifications.email_address', '');
        $this->webhookEnabled = Settings::get('notifications.webhook_enabled', false);
        $this->webhookUrl = Settings::get('notifications.webhook_url', '');
        $this->indexingCompletedNotify = Settings::get('notifications.indexing_completed', true);
        $this->indexingFailedNotify = Settings::get('notifications.indexing_failed', true);
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
    }

    public function render()
    {
        return view('livewire.admin.settings.notifications');
    }
}
