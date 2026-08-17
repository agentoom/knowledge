<?php

use App\Enums\Role;
use App\Knowledge\Models\KnowledgeSource;
use App\Livewire\Admin\KnowledgeSources\Create;
use App\Models\User;
use App\Providers\Markdown\MarkdownProvider;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin->value]);
});

// --- Preset population ---

test('markdown template populates the wizard state', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->call('applyTemplate', 'markdown_docs')
        ->assertHasNoErrors()
        ->assertSet('selectedTemplate', 'markdown_docs')
        ->assertSet('name', 'Documentation')
        ->assertSet('namespace', 'docs')
        ->assertSet('providerType', 'markdown');
});

test('filesystem template populates the wizard state', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->call('applyTemplate', 'filesystem_documents')
        ->assertSet('name', 'Documents')
        ->assertSet('namespace', 'documents')
        ->assertSet('providerType', 'filesystem');
});

test('web template maps its config into the urls field', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->call('applyTemplate', 'web_docs')
        ->assertSet('name', 'Website Documentation')
        ->assertSet('namespace', 'web-docs')
        ->assertSet('providerType', 'web')
        ->assertSet('configUrls', '');
});

test('sql template maps the named connection and blank table', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->call('applyTemplate', 'sql_table')
        ->assertSet('providerType', 'sql')
        ->assertSet('configConnectionName', 'pgsql')
        ->assertSet('configUseDynamicConnection', false)
        ->assertSet('configTable', '');
});

test('applying a template clears stale fields from a previous type', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('providerType', 'sql')
        ->set('configConnectionName', 'pgsql')
        ->set('configTable', 'orders')
        ->set('configPassword', 'secret')
        ->call('applyTemplate', 'markdown_docs')
        ->assertSet('providerType', 'markdown')
        ->assertSet('configConnectionName', '')
        ->assertSet('configTable', '')
        ->assertSet('configPassword', '');
});

// --- Round-trip through createSource ---

test('sql template config round-trips through source creation', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->call('applyTemplate', 'sql_table')
        ->set('configTable', 'users')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 2)
        ->call('nextStep')
        ->assertSet('step', 3);

    $source = KnowledgeSource::where('slug', 'sql-table')->first();

    expect($source)->not->toBeNull()
        ->and($source->provider_config['connection'])->toBe('pgsql')
        ->and($source->provider_config['table'])->toBe('users');
});

test('web template config round-trips through source creation', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->call('applyTemplate', 'web_docs')
        ->set('configUrls', "https://docs.example.com\nhttps://api.example.com")
        ->call('nextStep')
        ->assertHasNoErrors()
        ->call('nextStep')
        ->assertSet('step', 3);

    $source = KnowledgeSource::where('slug', 'website-documentation')->first();

    expect($source)->not->toBeNull()
        ->and($source->provider_config['urls'])->toBe([
            'https://docs.example.com',
            'https://api.example.com',
        ]);
});

test('sql template never persists plaintext credentials', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->call('applyTemplate', 'sql_table')
        ->set('configTable', 'customers')
        ->call('nextStep')
        ->call('nextStep');

    $source = KnowledgeSource::where('slug', 'sql-table')->first();

    expect($source)->not->toBeNull();

    $json = json_encode($source->provider_config);

    expect($json)->not->toContain('password')
        ->and($json)->not->toContain('secret');
});

test('markdown template creates a source that triggers the provider lifecycle', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->call('applyTemplate', 'markdown_docs')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->call('nextStep')
        ->assertSet('step', 3);

    $source = KnowledgeSource::where('slug', 'documentation')->first();

    expect($source)->not->toBeNull()
        ->and($source->providers()->count())->toBe(1)
        ->and($source->providers()->first()->class)->toBe(MarkdownProvider::class)
        ->and($source->providers()->first()->status)->toBe('active');
});

// --- Rejections ---

test('unknown templates leave the form unchanged and add a validation error', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->set('name', 'My Custom Source')
        ->call('applyTemplate', 'does_not_exist')
        ->assertHasErrors(['selectedTemplate'])
        ->assertSet('name', 'My Custom Source')
        ->assertSet('selectedTemplate', '');
});

test('applying a valid template clears a prior template error', function () {
    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->call('applyTemplate', 'does_not_exist')
        ->assertHasErrors(['selectedTemplate'])
        ->call('applyTemplate', 'sql_table')
        ->assertHasNoErrors()
        ->assertSet('selectedTemplate', 'sql_table')
        ->assertSet('providerType', 'sql');
});

test('duplicate template names produce validation feedback', function () {
    KnowledgeSource::create([
        'name' => 'Documentation',
        'slug' => 'documentation',
        'provider_type' => 'markdown',
        'namespace' => 'existing-docs',
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Create::class)
        ->call('applyTemplate', 'markdown_docs')
        ->assertSet('name', 'Documentation')
        ->call('nextStep')
        ->assertHasErrors(['name'])
        ->assertSet('step', 1);

    expect(KnowledgeSource::where('slug', 'documentation')->count())->toBe(1);
});
