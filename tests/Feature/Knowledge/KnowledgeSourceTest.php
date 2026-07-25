<?php

use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Knowledge\Models\Provider;

test('knowledge source can be created', function () {
    $source = KnowledgeSource::create([
        'name' => 'Test Source',
        'slug' => 'test-source',
        'provider_type' => 'filesystem',
        'namespace' => 'test',
    ]);

    expect($source->name)->toBe('Test Source')
        ->and($source->slug)->toBe('test-source')
        ->and($source->namespace)->toBe('test');
});

test('knowledge source has config version tracking', function () {
    $source = KnowledgeSource::create([
        'name' => 'Versioned Source',
        'slug' => 'versioned-source',
        'provider_type' => 'filesystem',
        'namespace' => 'test',
        'config_version' => 3,
    ]);

    expect($source->config_version)->toBe(3);
});

test('knowledge source can be filtered by namespace', function () {
    KnowledgeSource::create([
        'name' => 'Source A',
        'slug' => 'source-a',
        'provider_type' => 'filesystem',
        'namespace' => 'docs',
    ]);

    KnowledgeSource::create([
        'name' => 'Source B',
        'slug' => 'source-b',
        'provider_type' => 'sql',
        'namespace' => 'erp',
    ]);

    $result = KnowledgeSource::where('namespace', 'docs')->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->namespace)->toBe('docs');
});

test('knowledge source can be activated and deactivated', function () {
    $source = KnowledgeSource::create([
        'name' => 'Toggle Source',
        'slug' => 'toggle-source',
        'provider_type' => 'filesystem',
        'namespace' => 'test',
        'is_active' => true,
    ]);

    expect($source->is_active)->toBeTrue();

    $source->update(['is_active' => false]);

    expect($source->fresh()->is_active)->toBeFalse();
});

test('deleting knowledge source cascade-deletes providers', function () {
    $source = KnowledgeSource::create([
        'name' => 'Cascade Source',
        'slug' => 'cascade-source',
        'provider_type' => 'filesystem',
        'namespace' => 'cascade',
    ]);

    $provider = Provider::create([
        'knowledge_source_id' => $source->id,
        'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
        'name' => 'Cascade Provider',
        'type' => 'filesystem',
        'status' => 'active',
    ]);

    expect(Provider::where('knowledge_source_id', $source->id)->count())->toBe(1);

    $source->delete();

    expect(Provider::find($provider->id))->toBeNull();
    expect(Provider::where('knowledge_source_id', $source->id)->count())->toBe(0);
});

test('deleting knowledge source cascade-deletes documents', function () {
    $source = KnowledgeSource::create([
        'name' => 'Doc Cascade Source',
        'slug' => 'doc-cascade-source',
        'provider_type' => 'filesystem',
        'namespace' => 'doc-cascade',
    ]);

    $document = Document::create([
        'knowledge_source_id' => $source->id,
        'path' => '/test/path.txt',
        'filename' => 'test.txt',
        'size_bytes' => 100,
        'status' => 'discovered',
    ]);

    expect(Document::where('knowledge_source_id', $source->id)->count())->toBe(1);

    $source->delete();

    expect(Document::find($document->id))->toBeNull();
});
