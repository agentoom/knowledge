<?php

use App\Settings\Facades\Settings;

test('settings can be written and read from database', function () {
    Settings::set('test.key', 'test-value');

    $value = Settings::get('test.key');

    expect($value)->toBe('test-value');
});

test('settings returns default when key not found', function () {
    $value = Settings::get('nonexistent.key', 'default-value');

    expect($value)->toBe('default-value');
});

test('settings can store and retrieve values', function () {
    Settings::set('test.number', '42');

    $value = Settings::get('test.number');

    expect($value)->toBe('42');
});

test('settings facade is accessible', function () {
    Settings::set('test.facade', 'enabled');

    expect(Settings::get('test.facade'))->toBe('enabled');
});

test('settings can be overwritten', function () {
    Settings::set('test.overwrite', 'first');
    Settings::set('test.overwrite', 'second');

    expect(Settings::get('test.overwrite'))->toBe('second');
});
