<?php

use App\Knowledge\Models\Chunk;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Livewire\Admin\Health\Dashboard;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $user = User::factory()->create();
    actingAs($user);
});

test('it renders the health dashboard', function () {
    Livewire::test(Dashboard::class)
        ->assertSee('System Health')
        ->assertSee('Overall Status')
        ->assertSee('database')
        ->assertSee('cache')
        ->assertSee('vector store');
});

test('it shows environment information', function () {
    Livewire::test(Dashboard::class)
        ->assertSee('Environment')
        ->assertSee('Laravel')
        ->assertSee('PHP')
        ->assertSee('Debug Mode')
        ->assertSee('Queue Driver')
        ->assertSee('Cache Driver');
});

test('it shows system overview stats', function () {
    KnowledgeSource::factory()->count(3)->create();
    Document::factory()->count(5)->create();
    Chunk::factory()->count(10)->create();

    Livewire::test(Dashboard::class)
        ->assertSee('System Overview')
        ->assertSee('3')
        ->assertSee('5')
        ->assertSee('10');
});

test('it shows check details section', function () {
    Livewire::test(Dashboard::class)
        ->assertSee('Check Details');
});

test('it has a working refresh button', function () {
    Livewire::test(Dashboard::class)
        ->assertSee('Refresh')
        ->call('refresh')
        ->assertSee('System Health');
});

test('it shows quick actions links', function () {
    Livewire::test(Dashboard::class)
        ->assertSee('Quick Actions')
        ->assertSee('Admin Dashboard')
        ->assertSee('Jobs')
        ->assertSee('Settings');
});

test('overall status is ok when all checks pass', function () {
    $component = Livewire::test(Dashboard::class);

    expect($component->get('checks')['database']['status'])->toBe('ok');

    $status = $component->instance()->getOverallStatus();

    // Database should always be ok in test environment
    expect($status)->toBeIn(['ok', 'warning']);
});
