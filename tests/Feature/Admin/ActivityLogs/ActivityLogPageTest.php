<?php

use App\Enums\Role;
use App\Knowledge\Models\KnowledgeSource;
use App\Livewire\Admin\ActivityLogs\Index;
use App\Models\ActivityLog;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin->value]);
    $this->actingAs($this->admin);
});

test('activity log page renders for an admin', function () {
    $this->get(route('admin.activity-log'))
        ->assertOk()
        ->assertSee('Activity Log');
});

test('viewer role is denied the activity log route', function () {
    $viewer = User::factory()->create(['role' => Role::Viewer->value]);

    $this->actingAs($viewer)
        ->get(route('admin.activity-log'))
        ->assertForbidden();
});

test('page lists actor, action, subject, and redacted properties', function () {
    $source = KnowledgeSource::create([
        'name' => 'Audit Row Source',
        'slug' => 'audit-row-source',
        'provider_type' => 'filesystem',
        'namespace' => 'docs',
        'is_active' => true,
    ]);

    Livewire::test(Index::class)
        ->assertSee('knowledge_source.created')
        ->assertSee('Audit Row Source')
        ->assertSee($this->admin->name);
});

test('action filter narrows the result set', function () {
    KnowledgeSource::create([
        'name' => 'First Source',
        'slug' => 'first-source',
        'provider_type' => 'filesystem',
        'namespace' => 'docs',
        'is_active' => true,
    ]);

    ActivityLog::create([
        'user_id' => $this->admin->id,
        'action' => 'api_key.created',
        'properties' => ['name' => 'Key Row'],
        'ip_address' => '127.0.0.1',
    ]);

    Livewire::test(Index::class)
        ->set('filterAction', 'api_key.created')
        ->assertSee('api_key.created')
        ->assertDontSee('knowledge_source.created');
});

test('actor filter narrows the result set', function () {
    $other = User::factory()->create(['name' => 'Zed Other']);

    ActivityLog::create(['user_id' => $this->admin->id, 'action' => 'one.action', 'properties' => []]);
    ActivityLog::create(['user_id' => $other->id, 'action' => 'two.action', 'properties' => []]);

    Livewire::test(Index::class)
        ->set('filterActor', 'Zed Other')
        ->assertSee('two.action')
        ->assertDontSee('one.action');
});

test('system actor filter matches rows without a user', function () {
    ActivityLog::create(['user_id' => $this->admin->id, 'action' => 'user.action', 'properties' => []]);
    ActivityLog::create(['user_id' => null, 'action' => 'system.action', 'properties' => []]);

    Livewire::test(Index::class)
        ->set('filterActor', 'system')
        ->assertSee('system.action')
        ->assertDontSee('user.action');
});

test('newest entries appear first', function () {
    ActivityLog::create(['user_id' => $this->admin->id, 'action' => 'first.action', 'properties' => []])
        ->update(['created_at' => now()->subHour()]);
    ActivityLog::create(['user_id' => $this->admin->id, 'action' => 'latest.action', 'properties' => []]);

    Livewire::test(Index::class)
        ->assertSeeInOrder(['latest.action', 'first.action']);
});

test('page paginates at twenty entries', function () {
    for ($i = 0; $i < 25; $i++) {
        ActivityLog::create([
            'user_id' => $this->admin->id,
            'action' => "bulk.action.{$i}",
            'properties' => [],
        ]);
    }

    Livewire::test(Index::class)
        ->assertViewHas('logs', fn ($logs) => $logs->total() === 25 && $logs->count() === 20);

    Livewire::test(Index::class)
        ->call('gotoPage', 2)
        ->assertViewHas('logs', fn ($logs) => $logs->count() === 5);
});

test('empty state renders when no entries match', function () {
    Livewire::test(Index::class)
        ->assertSee('No activity log entries found');
});
