<?php

use App\Contracts\KnowledgeProvider;
use App\Contracts\ResultFusionStrategy;
use App\Knowledge\Models\Provider;
use App\Knowledge\Services\ProviderManager;
use App\Providers\Federation\FederationProvider;
use App\Retrieval\Models\ExecutionPlan;
use App\Retrieval\Models\PlanStep;
use App\Retrieval\Models\SearchResult;
use App\Retrieval\Services\RetrievalEngine;
use Illuminate\Concurrency\SyncDriver;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Http;

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

test('retrieval engine executes remote federation steps via Http::pool', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://remote1.example.com/api' => Http::response([
            'result' => [
                'content' => [
                    [
                        'text' => json_encode([
                            'items' => [
                                ['id' => 'r1', 'title' => 'Remote Result 1'],
                            ],
                        ]),
                    ],
                ],
            ],
        ]),
        'https://remote2.example.com/api' => Http::response([
            'result' => [
                'content' => [
                    [
                        'text' => json_encode([
                            'items' => [
                                ['id' => 'r2', 'title' => 'Remote Result 2'],
                            ],
                        ]),
                    ],
                ],
            ],
        ]),
    ]);

    $mockProviderManager = Mockery::mock(ProviderManager::class);
    $this->instance(ProviderManager::class, $mockProviderManager);

    $fusion = Mockery::mock(ResultFusionStrategy::class);
    $fusion->shouldReceive('fuse')
        ->once()
        ->andReturn([['id' => 'r1', 'title' => 'Remote Result 1'], ['id' => 'r2', 'title' => 'Remote Result 2']]);

    $engine = new RetrievalEngine($fusion, $mockProviderManager);

    $federationProvider1 = new FederationProvider(
        endpointUrl: 'https://remote1.example.com/api',
        authToken: 'token-1',
        serverName: 'remote-server-1',
    );

    $federationProvider2 = new FederationProvider(
        endpointUrl: 'https://remote2.example.com/api',
        authToken: 'token-2',
        serverName: 'remote-server-2',
    );

    $plan = new ExecutionPlan(steps: [
        new PlanStep(
            providerClass: '__federation__',
            operation: 'search',
            parameters: ['query' => 'test', 'namespace' => null, 'maxResults' => 10, 'filters' => [], '_federation_provider' => $federationProvider1],
            priority: 10,
        ),
        new PlanStep(
            providerClass: '__federation__',
            operation: 'search',
            parameters: ['query' => 'test', 'namespace' => null, 'maxResults' => 10, 'filters' => [], '_federation_provider' => $federationProvider2],
            priority: 20,
        ),
    ], strategy: 'federation');

    $result = $engine->execute($plan);

    expect($result->items)->toHaveCount(2)
        ->and($result->providerName)->toBe('fused');

    Http::assertSentCount(2);
});

test('retrieval engine handles failed remote federation response', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://down.example.com/api' => Http::response('Internal Error', 500),
    ]);

    $mockProviderManager = Mockery::mock(ProviderManager::class);
    $this->instance(ProviderManager::class, $mockProviderManager);

    $fusion = Mockery::mock(ResultFusionStrategy::class);
    $fusion->shouldReceive('fuse')
        ->once()
        ->andReturn([]);

    $engine = new RetrievalEngine($fusion, $mockProviderManager);

    $federationProvider = new FederationProvider(
        endpointUrl: 'https://down.example.com/api',
        authToken: 'token',
        serverName: 'down-server',
    );

    $plan = new ExecutionPlan(steps: [
        new PlanStep(
            providerClass: '__federation__',
            operation: 'search',
            parameters: ['query' => 'test', 'namespace' => null, 'maxResults' => 10, 'filters' => [], '_federation_provider' => $federationProvider],
            priority: 10,
        ),
    ], strategy: 'federation');

    $result = $engine->execute($plan);

    expect($result->items)->toBeEmpty()
        ->and($result->providerName)->toBe('fused');
});

test('retrieval engine mixes local and remote providers', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://remote.example.com/api' => Http::response([
            'result' => [
                'content' => [
                    [
                        'text' => json_encode([
                            'items' => [
                                ['id' => 'r', 'title' => 'Remote'],
                            ],
                        ]),
                    ],
                ],
            ],
        ]),
    ]);

    // Local provider mock
    $localProvider = Mockery::mock(KnowledgeProvider::class);
    $localProvider->shouldReceive('search')
        ->once()
        ->andReturn(new SearchResult(
            items: [['id' => 'l', 'title' => 'Local']],
            totalCount: 1,
            providerName: 'local',
        ));

    $mockProviderModel = Mockery::mock(Provider::class);
    $mockProviderModel->shouldReceive('toKnowledgeProvider')
        ->once()
        ->andReturn($localProvider);

    $mockProviderManager = Mockery::mock(ProviderManager::class);
    $this->instance(ProviderManager::class, $mockProviderManager);
    $mockProviderManager->shouldReceive('getByClass')
        ->with('App\Providers\Filesystem\FilesystemProvider')
        ->once()
        ->andReturn($mockProviderModel);

    $fusion = Mockery::mock(ResultFusionStrategy::class);
    $fusion->shouldReceive('fuse')
        ->once()
        ->andReturn([['id' => 'r', 'title' => 'Remote'], ['id' => 'l', 'title' => 'Local']]);

    $engine = new RetrievalEngine($fusion, $mockProviderManager);

    $federationProvider = new FederationProvider(
        endpointUrl: 'https://remote.example.com/api',
        authToken: 'token',
        serverName: 'remote',
    );

    $plan = new ExecutionPlan(steps: [
        // Local provider step
        new PlanStep(
            providerClass: 'App\Providers\Filesystem\FilesystemProvider',
            operation: 'search',
            parameters: ['query' => 'test', 'namespace' => null, 'maxResults' => 10, 'filters' => []],
            priority: 10,
        ),
        // Remote federation step
        new PlanStep(
            providerClass: '__federation__',
            operation: 'search',
            parameters: ['query' => 'test', 'namespace' => null, 'maxResults' => 10, 'filters' => [], '_federation_provider' => $federationProvider],
            priority: 60,
        ),
    ], strategy: 'federation');

    $result = $engine->execute($plan);

    expect($result->items)->toHaveCount(2)
        ->and($result->providerName)->toBe('fused');

    Http::assertSentCount(1);
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
