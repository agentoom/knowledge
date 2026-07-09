<?php

use App\Enums\Role;
use App\Knowledge\Models\KnowledgeSource;
use App\Livewire\Admin\KnowledgeSources\Index;
use App\Livewire\Admin\KnowledgeSources\Show;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin->value]);
});

// --- Create ---

test('can create knowledge source with sql named connection', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('name', 'SQL Named Source')
        ->set('namespace', 'erp')
        ->set('providerType', 'sql')
        ->set('configConnectionName', 'pgsql')
        ->set('configTable', 'users')
        ->call('create')
        ->assertHasNoErrors();

    $source = KnowledgeSource::where('name', 'SQL Named Source')->first();
    expect($source)->not->toBeNull()
        ->and($source->provider_config['connection'])->toBe('pgsql')
        ->and($source->provider_config['table'])->toBe('users');
});

test('can create knowledge source with sql dynamic connection', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('name', 'SQL Dynamic Source')
        ->set('namespace', 'external')
        ->set('providerType', 'sql')
        ->set('configUseDynamicConnection', true)
        ->set('configDriver', 'mysql')
        ->set('configHost', 'db.example.com')
        ->set('configPort', '3306')
        ->set('configDatabase', 'production')
        ->set('configUsername', 'admin')
        ->set('configPassword', 'secret123')
        ->set('configTable', 'orders')
        ->call('create')
        ->assertHasNoErrors();

    $source = KnowledgeSource::where('name', 'SQL Dynamic Source')->first();
    expect($source)->not->toBeNull()
        ->and($source->provider_config['table'])->toBe('orders')
        ->and($source->provider_config['connection'])->toBe([
            'driver' => 'mysql',
            'host' => 'db.example.com',
            'port' => 3306,
            'database' => 'production',
            'username' => 'admin',
            'password' => 'secret123',
        ]);
});

test('can create knowledge source with filesystem config', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('name', 'Filesystem Source')
        ->set('namespace', 'docs')
        ->set('providerType', 'filesystem')
        ->set('configBasePath', '/var/data/docs')
        ->call('create')
        ->assertHasNoErrors();

    $source = KnowledgeSource::where('name', 'Filesystem Source')->first();
    expect($source)->not->toBeNull()
        ->and($source->provider_config)->toBe(['basePath' => '/var/data/docs']);
});

test('can create knowledge source with web config', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('name', 'Web Source')
        ->set('namespace', 'external')
        ->set('providerType', 'web')
        ->set('configUrls', "https://docs.example.com\nhttps://api.example.com")
        ->call('create')
        ->assertHasNoErrors();

    $source = KnowledgeSource::where('name', 'Web Source')->first();
    expect($source)->not->toBeNull()
        ->and($source->provider_config['urls'])->toBe([
            'https://docs.example.com',
            'https://api.example.com',
        ]);
});

// --- Validation ---

test('sql source requires table when creating', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('name', 'No Table Source')
        ->set('namespace', 'test')
        ->set('providerType', 'sql')
        ->set('configConnectionName', 'pgsql')
        ->call('create')
        ->assertHasErrors(['configTable']);
});

test('sql dynamic connection requires host and database', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('name', 'Incomplete SQL')
        ->set('namespace', 'test')
        ->set('providerType', 'sql')
        ->set('configUseDynamicConnection', true)
        ->call('create')
        ->assertHasErrors(['configHost', 'configDatabase', 'configTable']);
});

test('filesystem source requires base path', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('name', 'No Path Source')
        ->set('namespace', 'test')
        ->set('providerType', 'filesystem')
        ->call('create')
        ->assertHasErrors(['configBasePath']);
});

test('web source requires urls', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('name', 'No Urls Source')
        ->set('namespace', 'test')
        ->set('providerType', 'web')
        ->call('create')
        ->assertHasErrors(['configUrls']);
});

// --- Edit ---

test('can edit knowledge source and update sql config', function () {
    $source = KnowledgeSource::create([
        'name' => 'Old SQL Source',
        'slug' => 'old-sql-source',
        'provider_type' => 'sql',
        'namespace' => 'erp',
        'provider_config' => [
            'connection' => 'pgsql',
            'table' => 'users',
        ],
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('edit', $source->id)
        ->assertSet('editName', 'Old SQL Source')
        ->assertSet('editConfigConnectionName', 'pgsql')
        ->assertSet('editConfigTable', 'users')
        ->set('editName', 'Updated SQL Source')
        ->set('editConfigTable', 'customers')
        ->call('update')
        ->assertHasNoErrors();

    $source->refresh();
    expect($source->name)->toBe('Updated SQL Source')
        ->and($source->provider_config['connection'])->toBe('pgsql')
        ->and($source->provider_config['table'])->toBe('customers');
});

test('can edit knowledge source and change from named to dynamic sql connection', function () {
    $source = KnowledgeSource::create([
        'name' => 'Named Source',
        'slug' => 'named-source',
        'provider_type' => 'sql',
        'namespace' => 'erp',
        'provider_config' => [
            'connection' => 'pgsql',
            'table' => 'users',
        ],
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('edit', $source->id)
        ->assertSet('editConfigUseDynamicConnection', false)
        ->assertSet('editConfigConnectionName', 'pgsql')
        ->set('editConfigUseDynamicConnection', true)
        ->set('editConfigDriver', 'mysql')
        ->set('editConfigHost', 'new-db.example.com')
        ->set('editConfigDatabase', 'new_db')
        ->set('editConfigTable', 'new_table')
        ->call('update')
        ->assertHasNoErrors();

    $source->refresh();
    expect($source->provider_config['connection'])->toBe([
        'driver' => 'mysql',
        'host' => 'new-db.example.com',
        'port' => 3306,
        'database' => 'new_db',
        'username' => '',
        'password' => '',
    ]);
});

// --- Show page ---

test('show page loads sql source with structured config fields', function () {
    $source = KnowledgeSource::create([
        'name' => 'Show Source',
        'slug' => 'show-source',
        'provider_type' => 'sql',
        'namespace' => 'erp',
        'provider_config' => [
            'connection' => [
                'driver' => 'pgsql',
                'host' => 'db.internal',
                'port' => 5432,
                'database' => 'analytics',
                'username' => 'reader',
                'password' => 'pass',
            ],
            'table' => 'metrics',
        ],
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['source' => $source->id])
        ->assertSee('Show Source')
        ->assertSee('pgsql')
        ->assertSet('configUseDynamicConnection', true)
        ->assertSet('configDriver', 'pgsql')
        ->assertSet('configHost', 'db.internal')
        ->assertSet('configPort', '5432')
        ->assertSet('configDatabase', 'analytics')
        ->assertSet('configTable', 'metrics');
});

test('show page can save config via structured form', function () {
    $source = KnowledgeSource::create([
        'name' => 'Edit Config Source',
        'slug' => 'edit-config-source',
        'provider_type' => 'filesystem',
        'namespace' => 'docs',
        'provider_config' => ['basePath' => '/old/path'],
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['source' => $source->id])
        ->set('isEditingConfig', true)
        ->set('useFormEditor', true)
        ->set('configBasePath', '/new/path/to/docs')
        ->call('saveConfig')
        ->assertHasNoErrors()
        ->assertSet('isEditingConfig', false);

    $source->refresh();
    expect($source->provider_config)->toBe(['basePath' => '/new/path/to/docs']);
});

test('show page can save config via json editor', function () {
    $source = KnowledgeSource::create([
        'name' => 'Json Config Source',
        'slug' => 'json-config-source',
        'provider_type' => 'filesystem',
        'namespace' => 'docs',
        'provider_config' => ['basePath' => '/old/path'],
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['source' => $source->id])
        ->set('isEditingConfig', true)
        ->set('useFormEditor', false)
        ->set('configJson', json_encode(['basePath' => '/json/path']))
        ->call('saveConfig')
        ->assertHasNoErrors()
        ->assertSet('isEditingConfig', false);

    $source->refresh();
    expect($source->provider_config)->toBe(['basePath' => '/json/path']);
});

test('delete knowledge source removes it', function () {
    $source = KnowledgeSource::create([
        'name' => 'Delete Me',
        'slug' => 'delete-me',
        'provider_type' => 'filesystem',
        'namespace' => 'test',
    ]);

    expect(KnowledgeSource::find($source->id))->not->toBeNull();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('delete', $source->id)
        ->assertHasNoErrors();

    expect(KnowledgeSource::find($source->id))->toBeNull();
});

test('can toggle knowledge source active status', function () {
    $source = KnowledgeSource::create([
        'name' => 'Toggle Source',
        'slug' => 'toggle-source',
        'provider_type' => 'filesystem',
        'namespace' => 'test',
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('toggleActive', $source->id)
        ->assertHasNoErrors();

    expect($source->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('toggleActive', $source->id);

    expect($source->fresh()->is_active)->toBeTrue();
});

test('non-admin users cannot access knowledge sources page', function () {
    $viewer = User::factory()->create(['role' => Role::Viewer->value]);

    $this->actingAs($viewer)
        ->get(route('admin.knowledge-sources.index'))
        ->assertForbidden();
});
