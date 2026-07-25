<?php

use App\VectorStore\Models\VectorStore;
use App\VectorStore\Services\VectorStoreManager;

test('vector store can be created', function () {
    $store = VectorStore::create([
        'driver' => 'typesense',
        'config' => ['host' => 'localhost', 'port' => 8108],
        'is_active' => true,
    ]);

    expect($store->driver)->toBe('typesense')
        ->and($store->is_active)->toBeTrue()
        ->and($store->config['host'])->toBe('localhost');
});

test('vector store manager resolves default driver', function () {
    VectorStore::create([
        'driver' => 'typesense',
        'config' => ['host' => 'typesense', 'port' => 8108, 'protocol' => 'http', 'api_key' => 'xyz'],
        'is_active' => true,
    ]);

    $manager = app(VectorStoreManager::class);

    expect($manager->getDefaultDriver())->toBe('typesense');
});

test('vector store manager resolves driver by name', function () {
    $manager = app(VectorStoreManager::class);

    $driver = $manager->driver('typesense');

    expect($driver)->not->toBeNull();
});

test('vector store manager returns capabilities', function () {
    $manager = app(VectorStoreManager::class);

    $capabilities = $manager->capabilities('typesense');

    expect($capabilities)->toBeArray();
});
