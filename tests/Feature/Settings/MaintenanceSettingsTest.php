<?php

use App\Livewire\Admin\Settings\Maintenance;
use App\Models\User;
use App\Settings\Facades\Settings;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $user = User::factory()->create();
    actingAs($user);
});

test('maintenance settings page loads with defaults', function () {
    Livewire::test(Maintenance::class)
        ->assertSet('federationSyncInterval', 15)
        ->assertSet('logPruningEnabled', true)
        ->assertSet('logPruningAgeDays', 30)
        ->assertSet('retrievalLogPruningEnabled', true)
        ->assertSet('retrievalLogPruningAgeDays', 30)
        ->assertSee('Federation Sync');
});

test('maintenance settings can be saved', function () {
    Livewire::test(Maintenance::class)
        ->set('federationSyncInterval', 30)
        ->set('logPruningAgeDays', 60)
        ->set('logPruningEnabled', false)
        ->call('save')
        ->assertDispatched('notify')
        ->assertDispatched('settings-clean');

    expect((int) Settings::get('maintenance.federation_sync_interval'))->toBe(30);
    expect((int) Settings::get('maintenance.log_pruning_age_days'))->toBe(60);
    expect((bool) Settings::get('maintenance.log_pruning_enabled'))->toBeFalse();
});

test('maintenance validates interval minimum', function () {
    Livewire::test(Maintenance::class)
        ->set('federationSyncInterval', 0)
        ->call('save')
        ->assertHasErrors(['federationSyncInterval']);
});

test('maintenance validates log pruning age maximum', function () {
    Livewire::test(Maintenance::class)
        ->set('logPruningAgeDays', 400)
        ->call('save')
        ->assertHasErrors(['logPruningAgeDays']);
});

test('retrieval log pruning can be toggled independently', function () {
    Livewire::test(Maintenance::class)
        ->set('retrievalLogPruningEnabled', false)
        ->set('retrievalLogPruningAgeDays', 90)
        ->call('save')
        ->assertDispatched('notify');

    expect((bool) Settings::get('maintenance.retrieval_log_pruning_enabled'))->toBeFalse();
    expect((int) Settings::get('maintenance.retrieval_log_pruning_age_days'))->toBe(90);
});
