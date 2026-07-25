<?php

use App\Enums\Role;
use App\Knowledge\Models\KnowledgeSource;
use App\Knowledge\Models\Provider;
use App\Livewire\Admin\Providers\Configure;
use App\Livewire\Admin\Providers\Index;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin->value]);
});

test('providers page lists database providers', function () {
    $source = KnowledgeSource::create([
        'name' => 'Test Source',
        'slug' => 'test-source',
        'provider_type' => 'filesystem',
        'namespace' => 'test',
    ]);

    $provider = Provider::create([
        'knowledge_source_id' => $source->id,
        'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
        'name' => 'Test Provider',
        'type' => 'filesystem',
        'status' => 'active',
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSee('Test Provider')
        ->assertSee('Test Source')
        ->assertSee('active');
});

test('providers page shows empty state when no providers exist', function () {
    Provider::query()->delete();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSee('No providers registered');
});

test('providers page shows source name when linked', function () {
    $source = KnowledgeSource::create([
        'name' => 'Linked Source',
        'slug' => 'linked-source',
        'provider_type' => 'filesystem',
        'namespace' => 'linked',
    ]);

    Provider::create([
        'knowledge_source_id' => $source->id,
        'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
        'name' => 'Linked Provider',
        'type' => 'filesystem',
        'status' => 'active',
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSee('Linked Source');
});

test('configure page loads provider data', function () {
    $source = KnowledgeSource::create([
        'name' => 'Config Source',
        'slug' => 'config-source',
        'provider_type' => 'filesystem',
        'namespace' => 'config-test',
    ]);

    $provider = Provider::create([
        'knowledge_source_id' => $source->id,
        'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
        'name' => 'Configurable Provider',
        'type' => 'sql',
        'status' => 'active',
        'metadata' => ['namespace' => 'ns', 'capabilities' => ['search']],
    ]);

    Livewire::actingAs($this->admin)
        ->test(Configure::class, ['provider' => $provider->id])
        ->assertSee('Configurable Provider')
        ->assertSet('name', 'Configurable Provider')
        ->assertSet('type', 'sql')
        ->assertSet('status', 'active')
        ->assertSee('Config Source');
});

test('configure page saves provider changes', function () {
    $source = KnowledgeSource::create([
        'name' => 'Save Source',
        'slug' => 'save-source',
        'provider_type' => 'filesystem',
        'namespace' => 'save-test',
    ]);

    $provider = Provider::create([
        'knowledge_source_id' => $source->id,
        'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
        'name' => 'Original Name',
        'type' => 'filesystem',
        'status' => 'active',
    ]);

    Livewire::actingAs($this->admin)
        ->test(Configure::class, ['provider' => $provider->id])
        ->set('name', 'Updated Provider Name')
        ->set('type', 'yaml')
        ->set('status', 'inactive')
        ->call('save')
        ->assertHasNoErrors();

    $provider->refresh();
    expect($provider->name)->toBe('Updated Provider Name')
        ->and($provider->type)->toBe('yaml')
        ->and($provider->status)->toBe('inactive');
});

test('configure page validates required fields', function () {
    $source = KnowledgeSource::create([
        'name' => 'Validate Source',
        'slug' => 'validate-source',
        'provider_type' => 'filesystem',
        'namespace' => 'validate',
    ]);

    $provider = Provider::create([
        'knowledge_source_id' => $source->id,
        'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
        'name' => 'Validate Me',
        'type' => 'filesystem',
        'status' => 'active',
    ]);

    Livewire::actingAs($this->admin)
        ->test(Configure::class, ['provider' => $provider->id])
        ->set('name', '')
        ->set('type', '')
        ->set('status', '')
        ->call('save')
        ->assertHasErrors(['name', 'type', 'status']);
});

test('configure page validates status values', function () {
    $source = KnowledgeSource::create([
        'name' => 'Status Source',
        'slug' => 'status-source',
        'provider_type' => 'filesystem',
        'namespace' => 'status',
    ]);

    $provider = Provider::create([
        'knowledge_source_id' => $source->id,
        'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
        'name' => 'Status Test',
        'type' => 'filesystem',
        'status' => 'active',
    ]);

    Livewire::actingAs($this->admin)
        ->test(Configure::class, ['provider' => $provider->id])
        ->set('status', 'invalid-status')
        ->call('save')
        ->assertHasErrors(['status']);
});

test('configure page validates metadata json', function () {
    $source = KnowledgeSource::create([
        'name' => 'Json Source',
        'slug' => 'json-source',
        'provider_type' => 'filesystem',
        'namespace' => 'json',
    ]);

    $provider = Provider::create([
        'knowledge_source_id' => $source->id,
        'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
        'name' => 'Json Test',
        'type' => 'filesystem',
        'status' => 'active',
    ]);

    Livewire::actingAs($this->admin)
        ->test(Configure::class, ['provider' => $provider->id])
        ->set('metadata', 'not valid json')
        ->call('save')
        ->assertHasErrors(['metadata']);
});

test('configure page 404s on invalid provider id', function () {
    Livewire::actingAs($this->admin)
        ->test(Configure::class, ['provider' => 99999]);
})->throws(ModelNotFoundException::class);

test('non-admin users cannot access providers page', function () {
    $viewer = User::factory()->create(['role' => Role::Viewer->value]);

    $this->actingAs($viewer)
        ->get(route('admin.providers.index'))
        ->assertForbidden();
});

test('providers index can sync a single provider', function () {
    $source = KnowledgeSource::create([
        'name' => 'Sync Source',
        'slug' => 'sync-source',
        'provider_type' => 'filesystem',
        'namespace' => 'sync-test',
    ]);

    $provider = Provider::create([
        'knowledge_source_id' => $source->id,
        'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
        'name' => 'Sync Provider',
        'type' => 'filesystem',
        'status' => 'error',
        'error_message' => 'Old error',
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('sync', $provider->id)
        ->assertHasNoErrors();

    $provider->refresh();
    expect($provider->status)->toBe('active')
        ->and($provider->error_message)->toBeNull()
        ->and($provider->last_synced_at)->not->toBeNull();
});

test('providers index can sync all providers', function () {
    $source1 = KnowledgeSource::create([
        'name' => 'Bulk Source 1',
        'slug' => 'bulk-source-1',
        'provider_type' => 'filesystem',
        'namespace' => 'bulk1',
    ]);

    $source2 = KnowledgeSource::create([
        'name' => 'Bulk Source 2',
        'slug' => 'bulk-source-2',
        'provider_type' => 'sql',
        'namespace' => 'bulk2',
    ]);

    Provider::create([
        'knowledge_source_id' => $source1->id,
        'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
        'name' => 'Bulk Provider 1',
        'type' => 'filesystem',
        'status' => 'error',
    ]);

    Provider::create([
        'knowledge_source_id' => $source2->id,
        'class' => 'App\\Providers\\Sql\\SqlProvider',
        'name' => 'Bulk Provider 2',
        'type' => 'sql',
        'status' => 'error',
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('syncAll')
        ->assertHasNoErrors();

    expect(Provider::where('status', 'active')->count())->toBe(2);
});
