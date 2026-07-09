<?php

use App\Knowledge\Models\KnowledgeSource;
use App\Knowledge\Models\Provider;

test('provider can be created with metadata', function () {
    $source = KnowledgeSource::create([
        'name' => 'Test Source',
        'slug' => 'test-provider-source',
        'provider_type' => 'filesystem',
        'namespace' => 'test',
    ]);

    $provider = Provider::create([
        'knowledge_source_id' => $source->id,
        'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
        'name' => 'Test Provider',
        'type' => 'filesystem',
        'metadata' => [
            'namespace' => 'test',
            'capabilities' => ['search'],
        ],
        'status' => 'active',
    ]);

    expect($provider->name)->toBe('Test Provider')
        ->and($provider->type)->toBe('filesystem')
        ->and($provider->status)->toBe('active')
        ->and($provider->metadata['namespace'])->toBe('test');
});

test('provider belongs to knowledge source', function () {
    $source = KnowledgeSource::create([
        'name' => 'Source X',
        'slug' => 'source-x',
        'provider_type' => 'filesystem',
        'namespace' => 'x',
    ]);

    $provider = Provider::create([
        'knowledge_source_id' => $source->id,
        'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
        'name' => 'Provider X',
        'type' => 'filesystem',
        'status' => 'active',
    ]);

    expect($provider->knowledgeSource->id)->toBe($source->id);
});

test('provider status can be updated', function () {
    $source = KnowledgeSource::create([
        'name' => 'Source Y',
        'slug' => 'source-y',
        'provider_type' => 'filesystem',
        'namespace' => 'y',
    ]);

    $provider = Provider::create([
        'knowledge_source_id' => $source->id,
        'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
        'name' => 'Provider Y',
        'type' => 'filesystem',
        'status' => 'active',
    ]);

    $provider->update(['status' => 'error', 'error_message' => 'Connection failed']);

    expect($provider->fresh()->status)->toBe('error')
        ->and($provider->fresh()->error_message)->toBe('Connection failed');
});
