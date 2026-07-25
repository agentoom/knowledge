<?php

namespace App\Listeners;

use App\Events\SettingsChanged;
use Illuminate\Support\Facades\Log;

class LogSettingsChange
{
    /**
     * Handle the event.
     */
    public function handle(SettingsChanged $event): void
    {
        Log::info('Settings changed', [
            'key' => $event->key,
            'type' => $event->type,
            'old_value' => $event->oldValue,
            'new_value' => $event->newValue,
            'user_id' => $event->userId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
