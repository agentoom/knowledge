<?php

use App\Livewire\Admin\Settings\RateLimiting;
use App\Models\User;
use App\Settings\Facades\Settings;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $user = User::factory()->create();
    actingAs($user);
});

test('rate limiting settings page loads with defaults', function () {
    Livewire::test(RateLimiting::class)
        ->assertSet('rateLimitingEnabled', true)
        ->assertSet('rateLimitPerMinute', 60)
        ->assertSee('MCP API Rate Limiting');
});

test('rate limiting settings can be saved', function () {
    Livewire::test(RateLimiting::class)
        ->set('rateLimitPerMinute', 120)
        ->set('rateLimitingEnabled', false)
        ->call('save')
        ->assertDispatched('notify')
        ->assertDispatched('settings-clean');

    expect((int) Settings::get('mcp.rate_limit_per_minute'))->toBe(120);
    expect((bool) Settings::get('mcp.rate_limiting_enabled'))->toBeFalse();
});

test('rate limiting validates minimum value', function () {
    Livewire::test(RateLimiting::class)
        ->set('rateLimitPerMinute', 0)
        ->call('save')
        ->assertHasErrors(['rateLimitPerMinute']);
});

test('rate limiting validates maximum value', function () {
    Livewire::test(RateLimiting::class)
        ->set('rateLimitPerMinute', 99999)
        ->call('save')
        ->assertHasErrors(['rateLimitPerMinute']);
});
