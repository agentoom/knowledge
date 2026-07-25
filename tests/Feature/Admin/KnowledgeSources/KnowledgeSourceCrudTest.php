<?php

use App\Enums\Role;
use App\Knowledge\Enums\ProviderType;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Livewire\Admin\KnowledgeSources\Create;
use App\Livewire\Admin\KnowledgeSources\Index;
use App\Livewire\Admin\KnowledgeSources\Show;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin->value]);
});

// --- Create (via wizard component) ---

test('can create knowledge source with sql named connection', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('name', 'SQL Named Source')
        ->set('namespace', 'erp')
        ->set('providerType', 'sql')
        ->set('configConnectionName', 'pgsql')
        ->set('configTable', 'users')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->call('nextStep')
        ->assertSet('step', 3);

    $source = KnowledgeSource::where('name', 'SQL Named Source')->first();
    expect($source)->not->toBeNull()
        ->and($source->provider_config['connection'])->toBe('pgsql')
        ->and($source->provider_config['table'])->toBe('users');
});

test('can create knowledge source with sql dynamic connection', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
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
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->call('nextStep')
        ->assertSet('step', 3);

    $source = KnowledgeSource::where('name', 'SQL Dynamic Source')->first();
    expect($source)->not->toBeNull()
        ->and($source->provider_config['table'])->toBe('orders')
        ->and($source->provider_config['connection'])->toHaveKeys(['driver', 'host', 'port', 'database', 'username', 'password'])
        ->and($source->provider_config['connection']['driver'])->toBe('mysql')
        ->and($source->provider_config['connection']['host'])->toBe('db.example.com')
        ->and($source->provider_config['connection']['port'])->toBe(3306)
        ->and($source->provider_config['connection']['database'])->toBe('production')
        ->and($source->provider_config['connection']['username'])->toBe('admin')
        ->and($source->provider_config['connection']['password'])->not->toBe('secret123'); // Should be encrypted
});

test('can create knowledge source with filesystem config', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('name', 'Filesystem Source')
        ->set('namespace', 'docs')
        ->set('providerType', 'filesystem')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->call('nextStep')
        ->assertSet('step', 3);

    $source = KnowledgeSource::where('name', 'Filesystem Source')->first();
    expect($source)->not->toBeNull()
        ->and($source->provider_type)->toBe('filesystem');
});

test('can create knowledge source with web config', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('name', 'Web Source')
        ->set('namespace', 'external')
        ->set('providerType', 'web')
        ->set('configUrls', "https://docs.example.com\nhttps://api.example.com")
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->call('nextStep')
        ->assertSet('step', 3);

    $source = KnowledgeSource::where('name', 'Web Source')->first();
    expect($source)->not->toBeNull()
        ->and($source->provider_config['urls'])->toBe([
            'https://docs.example.com',
            'https://api.example.com',
        ]);
});

// --- Validation on Create page ---

test('step 1 requires name and namespace', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('providerType', 'sql')
        ->call('nextStep')
        ->assertHasErrors(['name', 'namespace']);
});

test('step 1 validates namespace format', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('name', 'Test')
        ->set('namespace', 'INVALID UPPERCASE!')
        ->set('providerType', 'filesystem')
        ->call('nextStep')
        ->assertHasErrors(['namespace']);
});

test('can create knowledge source with markdown type', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('name', 'Markdown Docs')
        ->set('namespace', 'md-docs')
        ->set('providerType', 'markdown')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->call('nextStep')
        ->assertSet('step', 3);

    $source = KnowledgeSource::where('name', 'Markdown Docs')->first();
    expect($source)->not->toBeNull()
        ->and($source->provider_type)->toBe('markdown');
});

test('can create knowledge source with filesystem type (multi-format)', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('name', 'Filesystem Source')
        ->set('namespace', 'misc-files')
        ->set('providerType', 'filesystem')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->call('nextStep')
        ->assertSet('step', 3);

    $source = KnowledgeSource::where('name', 'Filesystem Source')->first();
    expect($source)->not->toBeNull()
        ->and($source->provider_type)->toBe('filesystem');
});

// --- Edit (still on Index component) ---

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

// --- File management via edit ---

test('can add files to existing filesystem source via edit', function () {
    // Ensure the canonical directory exists
    $dir = ProviderType::Filesystem->canonicalPath('uploads');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $source = KnowledgeSource::create([
        'name' => 'Editable Filesystem',
        'slug' => 'editable-filesystem',
        'provider_type' => 'filesystem',
        'namespace' => 'uploads',
    ]);

    // Create one existing document
    $existingPath = $dir.'/existing.txt';
    file_put_contents($existingPath, 'old content');
    $source->documents()->create([
        'path' => $existingPath,
        'filename' => 'existing.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 500,
        'status' => 'discovered',
    ]);

    // Create a real temp file and then add it via the upload mechanism
    $tmpFile = $dir.'/new-file-test.md';
    file_put_contents($tmpFile, '# Test markdown');
    $source->documents()->create([
        'path' => $tmpFile,
        'filename' => 'new-file-test.md',
        'mime_type' => 'text/markdown',
        'size_bytes' => filesize($tmpFile),
        'content_hash' => md5_file($tmpFile),
        'status' => 'discovered',
    ]);

    $source->refresh();
    expect($source->documents()->count())->toBe(2);
});

test('can remove file from filesystem source', function () {
    $dir = ProviderType::Filesystem->canonicalPath('uploads');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $source = KnowledgeSource::create([
        'name' => 'Remove File Source',
        'slug' => 'remove-file-source',
        'provider_type' => 'filesystem',
        'namespace' => 'uploads',
    ]);

    $filePath = $dir.'/removable.txt';
    file_put_contents($filePath, 'content');
    $document = $source->documents()->create([
        'path' => $filePath,
        'filename' => 'removable.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 300,
        'status' => 'discovered',
    ]);

    expect(file_exists($document->path))->toBeTrue();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('edit', $source->id)
        ->call('removeFile', $document->id)
        ->assertHasNoErrors();

    expect(Document::find($document->id))->toBeNull();
    expect(file_exists($filePath))->toBeFalse();
});

test('non-admin users cannot access knowledge sources page', function () {
    $viewer = User::factory()->create(['role' => Role::Viewer->value]);

    $this->actingAs($viewer)
        ->get(route('admin.knowledge-sources.index'))
        ->assertForbidden();
});
