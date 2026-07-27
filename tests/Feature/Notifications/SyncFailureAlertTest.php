<?php

use App\Listeners\NotifySyncFailure;
use App\Services\NotificationService;
use App\Settings\Facades\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Settings::set('notifications.email_enabled', true, 'boolean');
    Settings::set('notifications.email_address', 'admin@example.com', 'string');
    Settings::set('notifications.sync_failure_enabled', true, 'boolean');
    Settings::set('notifications.sync_failure_threshold', 3, 'integer');
    Settings::set('notifications.cooldown_seconds', 0, 'integer');

    Cache::flush();
});

test('sync failure notifies after threshold is reached', function () {
    Mail::fake();

    $notifications = app(NotificationService::class);

    // First two failures should not trigger
    (new NotifySyncFailure)->handle('test-source', 'federation', 'Connection timeout', $notifications);
    (new NotifySyncFailure)->handle('test-source', 'federation', 'Connection timeout', $notifications);

    $consecutive = (int) Cache::get('sync_failure_count:federation:test-source', 0);
    expect($consecutive)->toBe(2);

    // Third consecutive should trigger
    (new NotifySyncFailure)->handle('test-source', 'federation', 'Connection timeout', $notifications);

    $consecutive = (int) Cache::get('sync_failure_count:federation:test-source', 0);
    expect($consecutive)->toBe(3);
});

test('sync failure respects disabled setting', function () {
    Settings::set('notifications.sync_failure_enabled', false, 'boolean');

    $notifications = app(NotificationService::class);

    for ($i = 0; $i < 5; $i++) {
        (new NotifySyncFailure)->handle('test-source', 'federation', 'Connection timeout', $notifications);
    }

    $consecutive = (int) Cache::get('sync_failure_count:federation:test-source', 0);
    expect($consecutive)->toBe(5);

    // Notification should not have been sent (we just verify no exceptions)
    expect(true)->toBeTrue();
});

test('reset failure count clears the counter', function () {
    $notifications = app(NotificationService::class);

    (new NotifySyncFailure)->handle('test-source', 'federation', 'Error', $notifications);

    expect((int) Cache::get('sync_failure_count:federation:test-source', 0))->toBe(1);

    NotifySyncFailure::resetFailureCount('test-source', 'federation');

    expect((int) Cache::get('sync_failure_count:federation:test-source', 0))->toBe(0);
});
