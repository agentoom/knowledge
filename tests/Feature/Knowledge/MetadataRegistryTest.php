<?php

use App\Knowledge\Models\MetadataRegistry;
use App\Knowledge\Services\MetadataRegistryService;

test('metadata registry can be built from providers', function () {
    $service = app(MetadataRegistryService::class);
    $registry = $service->build();

    expect($registry)->not->toBeNull()
        ->and($registry->version)->toBeGreaterThan(0)
        ->and($registry->checksum)->not->toBeEmpty()
        ->and($registry->payload)->toBeArray();
});

test('metadata registry get returns latest payload', function () {
    MetadataRegistry::create([
        'payload' => [
            'providers' => [
                [
                    'class' => 'TestProvider',
                    'namespace' => 'test',
                    'capabilities' => ['search'],
                    'resources' => ['docs'],
                    'fields' => ['content'],
                ],
            ],
            'schemas' => [],
            'resources' => ['docs'],
            'relationships' => [],
            'namespaces' => ['test'],
            'capabilities' => ['search'],
        ],
        'version' => 1,
        'checksum' => 'test123',
        'built_at' => now(),
    ]);

    $service = app(MetadataRegistryService::class);
    $data = $service->get();

    expect($data)->toBeArray()
        ->and($data['namespaces'])->toContain('test');
});

test('metadata registry returns empty array when no data exists', function () {
    MetadataRegistry::query()->delete();

    $service = app(MetadataRegistryService::class);
    $data = $service->get();

    expect($data)->toBe([]);
});

test('metadata registry checksum can be retrieved', function () {
    MetadataRegistry::create([
        'payload' => ['test' => true],
        'version' => 1,
        'checksum' => 'unique123',
        'built_at' => now(),
    ]);

    $service = app(MetadataRegistryService::class);
    $checksum = $service->getChecksum();

    expect($checksum)->toBe('unique123');
});
