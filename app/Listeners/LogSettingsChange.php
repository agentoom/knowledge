<?php

namespace App\Listeners;

use App\Events\SettingsChanged;
use App\Services\ActivityLogger;
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

        // Encrypted-typed settings arrive with their old value already
        // decrypted by the settings cast. Redact both values wholesale —
        // the key may be innocuous, so key-name redaction alone is not enough.
        $oldValue = $event->type === 'encrypted' ? '[REDACTED]' : $event->oldValue;
        $newValue = $event->type === 'encrypted' ? '[REDACTED]' : $event->newValue;

        app(ActivityLogger::class)->safeRecord('settings.updated', null, [
            'key' => $event->key,
            'type' => $event->type,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ], $event->userId);
    }
}
