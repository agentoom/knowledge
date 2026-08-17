<?php

use App\Auth\Services\ApiKeyService;
use App\Knowledge\Models\KnowledgeSource;
use App\Livewire\Admin\Settings\DangerZone;
use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Settings\Facades\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// --- Knowledge sources ---

test('knowledge source creation writes an audit row with the actor', function () {
    KnowledgeSource::create([
        'name' => 'Audited Source',
        'slug' => 'audited-source',
        'provider_type' => 'filesystem',
        'namespace' => 'docs',
        'is_active' => true,
    ]);

    $row = ActivityLog::where('action', 'knowledge_source.created')->latest()->first();

    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBe($this->user->id)
        ->and($row->subject_type)->toBe(KnowledgeSource::class)
        ->and($row->subject_id)->not->toBeNull()
        ->and($row->properties['name'])->toBe('Audited Source')
        ->and($row->properties['namespace'])->toBe('docs')
        ->and($row->properties)->not->toHaveKey('provider_config');
});

test('knowledge source update audits only changed safe fields', function () {
    $source = KnowledgeSource::create([
        'name' => 'Before Name',
        'slug' => 'before-name',
        'provider_type' => 'filesystem',
        'namespace' => 'docs',
        'is_active' => true,
    ]);

    ActivityLog::query()->delete();

    $source->update(['name' => 'After Name', 'description' => 'Renamed']);

    $row = ActivityLog::where('action', 'knowledge_source.updated')->latest()->first();

    expect($row)->not->toBeNull()
        ->and($row->properties)->toHaveKey('name', 'After Name')
        ->and($row->properties)->toHaveKey('description', 'Renamed')
        ->and($row->properties)->not->toHaveKey('provider_config');
});

test('knowledge source update redacts credentials inside provider_config', function () {
    $source = KnowledgeSource::create([
        'name' => 'Secret SQL Source',
        'slug' => 'secret-sql-source',
        'provider_type' => 'sql',
        'namespace' => 'erp',
        'provider_config' => [
            'connection' => ['driver' => 'pgsql', 'host' => 'db.local', 'password' => 'old-plaintext'],
            'table' => 'users',
        ],
        'is_active' => true,
    ]);

    ActivityLog::query()->delete();

    $source->update(['provider_config' => [
        'connection' => ['driver' => 'pgsql', 'host' => 'db.local', 'password' => 'new-plaintext'],
        'table' => 'orders',
    ]]);

    $row = ActivityLog::where('action', 'knowledge_source.updated')->latest()->first();

    expect($row)->not->toBeNull()
        ->and($row->properties['provider_config']['connection']['password'])->toBe('[REDACTED]')
        ->and($row->properties['provider_config']['table'])->toBe('orders');

    $propertiesJson = json_encode($row->properties);

    expect($propertiesJson)->not->toContain('new-plaintext')
        ->and($propertiesJson)->not->toContain('old-plaintext');
});

test('knowledge source deletion audits safe attributes', function () {
    $source = KnowledgeSource::create([
        'name' => 'Doomed Source',
        'slug' => 'doomed-source',
        'provider_type' => 'filesystem',
        'namespace' => 'docs',
        'is_active' => true,
    ]);

    ActivityLog::query()->delete();

    $source->delete();

    $row = ActivityLog::where('action', 'knowledge_source.deleted')->latest()->first();

    expect($row)->not->toBeNull()
        ->and($row->properties['name'])->toBe('Doomed Source')
        ->and($row->properties)->not->toHaveKey('provider_config');
});

// --- API keys ---

test('api key creation via service writes an audit row', function () {
    (new ApiKeyService)->create('Service Key', $this->user->id, ['knowledge:read'], ['docs']);

    $row = ActivityLog::where('action', 'api_key.created')->latest()->first();

    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBe($this->user->id)
        ->and($row->properties['name'])->toBe('Service Key')
        ->and($row->properties['key_prefix'])->not->toBeNull()
        ->and($row->properties)->not->toHaveKey('key')
        ->and($row->properties)->not->toHaveKey('plainKey')
        ->and(collect($row->properties)->flatten()->map(fn ($v) => (string) $v))->each->not->toContain('plain');
});

test('direct api key creation and deletion write audit rows', function () {
    $key = ApiKey::create([
        'user_id' => $this->user->id,
        'name' => 'Direct Key',
        'key' => Hash::make('a-very-long-plain-secret-that-must-never-leak'),
        'key_prefix' => 'a-very-l',
        'scopes' => ['mcp:use'],
        'knowledge_namespaces' => ['docs'],
    ]);

    $created = ActivityLog::where('action', 'api_key.created')->latest()->first();

    expect($created)->not->toBeNull()
        ->and($created->properties['key_prefix'])->toBe('a-very-l')
        ->and($created->properties['scopes'])->toBe(['mcp:use'])
        ->and($created->properties)->not->toHaveKey('key');

    $key->delete();

    $deleted = ActivityLog::where('action', 'api_key.deleted')->latest()->first();

    expect($deleted)->not->toBeNull()
        ->and($deleted->properties['name'])->toBe('Direct Key')
        ->and($deleted->properties)->not->toHaveKey('key');
});

test('api key updates audit array-cast fields as arrays', function () {
    $key = ApiKey::create([
        'user_id' => $this->user->id,
        'name' => 'Scope Changer',
        'key' => Hash::make('secret-value'),
        'key_prefix' => 'secret-v',
        'scopes' => ['mcp:use'],
    ]);

    ActivityLog::query()->delete();

    $key->update(['scopes' => ['mcp:use', 'knowledge:read']]);

    $row = ActivityLog::where('action', 'api_key.updated')->latest()->first();

    expect($row)->not->toBeNull()
        ->and($row->properties['scopes'])->toBe(['mcp:use', 'knowledge:read']);
});

test('api key updates audit changed fields but not last_used_at', function () {
    $key = ApiKey::create([
        'user_id' => $this->user->id,
        'name' => 'Rename Me',
        'key' => Hash::make('secret-value'),
        'key_prefix' => 'secret-v',
        'scopes' => ['mcp:use'],
    ]);

    ActivityLog::query()->delete();

    $key->update(['name' => 'Renamed Key']);
    $key->update(['last_used_at' => now()]);

    $rows = ActivityLog::where('action', 'api_key.updated')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->properties)->toHaveKey('name', 'Renamed Key')
        ->and($rows->first()->properties)->not->toHaveKey('last_used_at')
        ->and($rows->first()->properties)->not->toHaveKey('key');
});

// --- Settings ---

test('setting change writes an audit row preserving the key', function () {
    Settings::set('knowledge.test_setting', 'hello', 'string');

    $row = ActivityLog::where('action', 'settings.updated')->latest()->first();

    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBe($this->user->id)
        ->and($row->properties['key'])->toBe('knowledge.test_setting')
        ->and($row->properties['new_value'])->toBe('hello');
});

test('setting forget writes an audit row with a null new value', function () {
    Settings::set('knowledge.forgettable', 'old', 'string');

    ActivityLog::query()->delete();

    Settings::forget('knowledge.forgettable');

    $row = ActivityLog::where('action', 'settings.updated')->latest()->first();

    expect($row)->not->toBeNull()
        ->and($row->properties['key'])->toBe('knowledge.forgettable')
        ->and($row->properties['old_value'])->toBe('old')
        ->and($row->properties['new_value'])->toBeNull();
});

test('sensitive setting values are redacted but the key is preserved', function () {
    Settings::set('services.openai.api_key', 'sk-secret-token-value', 'string');

    $row = ActivityLog::where('action', 'settings.updated')->latest()->first();

    expect($row)->not->toBeNull()
        ->and($row->properties['key'])->toBe('services.openai.api_key')
        ->and($row->properties['new_value'])->toBe('[REDACTED]');

    $propertiesJson = json_encode($row->properties);

    expect($propertiesJson)->not->toContain('sk-secret-token-value')
        ->and($propertiesJson)->toContain('[REDACTED]');
});

test('nested password values inside audited properties are redacted', function () {
    Settings::set('knowledge.nested', ['connection' => ['password' => 'db-pass', 'host' => 'db.local']], 'json');

    $row = ActivityLog::where('action', 'settings.updated')->latest()->first();

    expect($row->properties['new_value']['connection']['password'])->toBe('[REDACTED]')
        ->and($row->properties['new_value']['connection']['host'])->toBe('db.local');
});

// --- Actor resolution ---

test('system writes allow a null actor', function () {
    Auth::logout();

    $logger = app(ActivityLogger::class);

    $row = $logger->record('knowledge_sync.completed', null, ['source' => 'cli']);

    expect($row->user_id)->toBeNull();
});

test('explicit user id wins over the authenticated actor', function () {
    $other = User::factory()->create();
    $logger = app(ActivityLogger::class);

    $row = $logger->record('test.explicit_actor', null, [], $other->id);

    expect($row->user_id)->toBe($other->id);
});

// --- Danger Zone reset ---

test('danger zone reset truncates the activity log', function () {
    Http::fake();

    KnowledgeSource::create([
        'name' => 'Reset Source',
        'slug' => 'reset-source',
        'provider_type' => 'filesystem',
        'namespace' => 'docs',
        'is_active' => true,
    ]);

    Settings::set('knowledge.some_key', 'value', 'string');

    expect(ActivityLog::count())->toBeGreaterThan(0);

    Livewire::actingAs($this->user)
        ->test(DangerZone::class)
        ->call('resetApp')
        ->assertSet('resultMessage', fn (?string $message) => str_contains($message ?? '', 'App reset complete'));

    expect(ActivityLog::count())->toBe(0);
});
