<?php

use App\Contracts\KnowledgeProvider;
use App\Contracts\ResultFusionStrategy;
use App\Knowledge\Models\Provider;
use App\Knowledge\Services\ProviderManager;
use App\Retrieval\Models\ExecutionPlan;
use App\Retrieval\Models\PlanStep;
use App\Retrieval\Models\SearchResult;
use App\Retrieval\Services\RetrievalEngine;
use Illuminate\Concurrency\SyncDriver;
use Illuminate\Support\Facades\Concurrency;

beforeEach(function () {
    Concurrency::swap(new SyncDriver);
});

function createProviderModelMock(?KnowledgeProvider $knowledgeProvider = null): Provider
{
    $model = Mockery::mock(Provider::class);
    $model->shouldReceive('toKnowledgeProvider')
        ->once()
        ->andReturn($knowledgeProvider);

    return $model;
}

test('retrieval engine resolves providers and executes search', function () {
    $mockKnowledgeProvider = Mockery::mock(KnowledgeProvider::class);
    $mockKnowledgeProvider->shouldReceive('search')
        ->once()
        ->andReturn(new SearchResult(
            items: [['id' => '1', 'filename' => 'test.txt']],
            totalCount: 1,
            providerName: 'test',
        ));

    $mockProviderModel = createProviderModelMock($mockKnowledgeProvider);

    $mockProviderManager = Mockery::mock(ProviderManager::class);
    $this->instance(ProviderManager::class, $mockProviderManager);
    $mockProviderManager->shouldReceive('getByClass')
        ->with('App\\Providers\\Filesystem\\FilesystemProvider')
        ->once()
        ->andReturn($mockProviderModel);

    $fusion = Mockery::mock(ResultFusionStrategy::class);
    $fusion->shouldReceive('fuse')
        ->once()
        ->andReturn([['id' => '1', 'filename' => 'test.txt']]);

    $engine = new RetrievalEngine($fusion, $mockProviderManager);

    $plan = new ExecutionPlan(steps: [
        new PlanStep(
            providerClass: 'App\\Providers\\Filesystem\\FilesystemProvider',
            operation: 'search',
            parameters: ['query' => 'test', 'namespace' => 'filesystem', 'maxResults' => 10, 'filters' => []],
            priority: 10,
        ),
    ], strategy: 'default');

    $result = $engine->execute($plan);

    expect($result->items)->toHaveCount(1)
        ->and($result->items[0]['filename'])->toBe('test.txt')
        ->and($result->providerName)->toBe('fused');
});

test('retrieval engine handles missing provider gracefully', function () {
    $mockProviderManager = Mockery::mock(ProviderManager::class);
    $this->instance(ProviderManager::class, $mockProviderManager);
    $mockProviderManager->shouldReceive('getByClass')
        ->with('App\\Providers\\Nonexistent\\NonexistentProvider')
        ->once()
        ->andReturn(null);

    $fusion = Mockery::mock(ResultFusionStrategy::class);
    $fusion->shouldReceive('fuse')
        ->once()
        ->andReturn([]);

    $engine = new RetrievalEngine($fusion, $mockProviderManager);

    $plan = new ExecutionPlan(steps: [
        new PlanStep(
            providerClass: 'App\\Providers\\Nonexistent\\NonexistentProvider',
            operation: 'search',
            parameters: ['query' => 'test'],
            priority: 10,
        ),
    ], strategy: 'default');

    $result = $engine->execute($plan);

    expect($result->items)->toBeEmpty()
        ->and($result->providerName)->toBe('fused');
});

test('retrieval engine fuses results from multiple providers', function () {
    $provider1 = Mockery::mock(KnowledgeProvider::class);
    $provider1->shouldReceive('search')
        ->once()
        ->andReturn(new SearchResult(
            items: [['id' => 'a', 'title' => 'Result A']],
            totalCount: 1,
            providerName: 'provider1',
        ));

    $provider2 = Mockery::mock(KnowledgeProvider::class);
    $provider2->shouldReceive('search')
        ->once()
        ->andReturn(new SearchResult(
            items: [['id' => 'b', 'title' => 'Result B']],
            totalCount: 1,
            providerName: 'provider2',
        ));

    $mockProviderModel1 = createProviderModelMock($provider1);
    $mockProviderModel2 = createProviderModelMock($provider2);

    $mockProviderManager = Mockery::mock(ProviderManager::class);
    $this->instance(ProviderManager::class, $mockProviderManager);
    $mockProviderManager->shouldReceive('getByClass')
        ->with('App\\Providers\\Filesystem\\FilesystemProvider')
        ->once()
        ->andReturn($mockProviderModel1);
    $mockProviderManager->shouldReceive('getByClass')
        ->with('App\\Providers\\Sql\\SqlProvider')
        ->once()
        ->andReturn($mockProviderModel2);

    $fusion = Mockery::mock(ResultFusionStrategy::class);
    $fusion->shouldReceive('fuse')
        ->once()
        ->andReturn([['id' => 'a', 'title' => 'Result A'], ['id' => 'b', 'title' => 'Result B']]);

    $engine = new RetrievalEngine($fusion, $mockProviderManager);

    $plan = new ExecutionPlan(steps: [
        new PlanStep(
            providerClass: 'App\\Providers\\Filesystem\\FilesystemProvider',
            operation: 'search',
            parameters: ['query' => 'test'],
            priority: 10,
        ),
        new PlanStep(
            providerClass: 'App\\Providers\\Sql\\SqlProvider',
            operation: 'search',
            parameters: ['query' => 'test'],
            priority: 20,
        ),
    ], strategy: 'default');

    $result = $engine->execute($plan);

    expect($result->items)->toHaveCount(2)
        ->and($result->providerName)->toBe('fused');
});
