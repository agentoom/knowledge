<?php

use App\Contracts\RemoteProvider;
use App\Providers\Federation\FederationProvider;
use App\Retrieval\Models\SearchQuery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('FederationProvider implements RemoteProvider', function () {
    $provider = new FederationProvider(
        endpointUrl: 'https://remote.example.com/api',
        authToken: 'secret-token',
        serverName: 'test-server',
    );

    expect($provider)->toBeInstanceOf(RemoteProvider::class);
});

test('getEndpointUrl returns the configured endpoint', function () {
    $provider = new FederationProvider(
        endpointUrl: 'https://remote.example.com/api',
        authToken: 'secret-token',
        serverName: 'test-server',
    );

    expect($provider->getEndpointUrl())->toBe('https://remote.example.com/api');
});

test('getAuthToken returns the configured token', function () {
    $provider = new FederationProvider(
        endpointUrl: 'https://remote.example.com/api',
        authToken: 'secret-token',
        serverName: 'test-server',
    );

    expect($provider->getAuthToken())->toBe('secret-token');
});

test('getServerName returns the configured server name', function () {
    $provider = new FederationProvider(
        endpointUrl: 'https://remote.example.com/api',
        authToken: 'secret-token',
        serverName: 'test-server',
    );

    expect($provider->getServerName())->toBe('test-server');
});

test('buildSearchPayload returns correct JSON-RPC structure', function () {
    $provider = new FederationProvider(
        endpointUrl: 'https://remote.example.com/api',
        authToken: 'secret-token',
        serverName: 'test-server',
    );

    $query = new SearchQuery(
        query: 'test query',
        namespace: 'docs',
        maxResults: 15,
        filters: ['type' => 'pdf'],
        searchType: 'semantic',
    );

    $payload = $provider->buildSearchPayload($query);

    expect($payload)->toMatchArray([
        'jsonrpc' => '2.0',
        'method' => 'tools/call',
        'id' => 1,
    ]);

    expect($payload['params']['name'])->toBe('search_knowledge');
    expect($payload['params']['arguments']['query'])->toBe('test query');
    expect($payload['params']['arguments']['namespace'])->toBe('docs');
    expect($payload['params']['arguments']['max_results'])->toBe(15);
    expect($payload['params']['arguments']['filters'])->toBe(['type' => 'pdf']);
    expect($payload['params']['arguments']['search_type'])->toBe('semantic');
});

test('parseSearchResponse extracts items from valid response', function () {
    $provider = new FederationProvider(
        endpointUrl: 'https://remote.example.com/api',
        authToken: 'secret-token',
        serverName: 'test-server',
    );

    $body = [
        'result' => [
            'content' => [
                [
                    'text' => json_encode([
                        'items' => [
                            ['id' => '1', 'title' => 'Doc One'],
                            ['id' => '2', 'title' => 'Doc Two'],
                        ],
                    ]),
                ],
            ],
        ],
    ];

    $result = $provider->parseSearchResponse($body, 'test-server');

    expect($result->totalCount)->toBe(2)
        ->and($result->providerName)->toBe('federation.test-server')
        ->and($result->items)->toHaveCount(2)
        ->and($result->items[0]['_federation_source'])->toBe('test-server')
        ->and($result->items[1]['_federation_source'])->toBe('test-server')
        ->and($result->items[0]['title'])->toBe('Doc One');
});

test('parseSearchResponse handles missing content gracefully', function () {
    $provider = new FederationProvider(
        endpointUrl: 'https://remote.example.com/api',
        authToken: 'secret-token',
        serverName: 'test-server',
    );

    $body = ['result' => []];

    $result = $provider->parseSearchResponse($body, 'test-server');

    expect($result->items)->toBeEmpty()
        ->and($result->totalCount)->toBe(0);
});

test('parseSearchResponse handles non-array result content', function () {
    $provider = new FederationProvider(
        endpointUrl: 'https://remote.example.com/api',
        authToken: 'secret-token',
        serverName: 'test-server',
    );

    $body = [
        'result' => [
            'content' => [
                [
                    'text' => '"not an array"',
                ],
            ],
        ],
    ];

    $result = $provider->parseSearchResponse($body, 'test-server');

    expect($result->items)->toBeEmpty()
        ->and($result->totalCount)->toBe(0);
});

test('search makes HTTP request to remote endpoint', function () {
    Http::fake([
        'https://remote.example.com/api' => Http::response([
            'result' => [
                'content' => [
                    [
                        'text' => json_encode([
                            'items' => [
                                ['id' => 'a', 'title' => 'Alpha'],
                            ],
                        ]),
                    ],
                ],
            ],
        ]),
    ]);

    $provider = new FederationProvider(
        endpointUrl: 'https://remote.example.com/api',
        authToken: 'secret-token',
        serverName: 'test-server',
    );

    $query = new SearchQuery(query: 'test');
    $result = $provider->search($query);

    expect($result->items)->toHaveCount(1)
        ->and($result->items[0]['title'])->toBe('Alpha')
        ->and($result->items[0]['_federation_source'])->toBe('test-server');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://remote.example.com/api'
            && $request->hasHeader('Authorization', 'Bearer secret-token')
            && $request['method'] === 'tools/call';
    });
});

test('search returns empty result on failed HTTP response', function () {
    Http::fake([
        'https://remote.example.com/api' => Http::response('Server Error', 500),
    ]);

    $provider = new FederationProvider(
        endpointUrl: 'https://remote.example.com/api',
        authToken: 'secret-token',
        serverName: 'test-server',
    );

    $query = new SearchQuery(query: 'test');
    $result = $provider->search($query);

    expect($result->items)->toBeEmpty()
        ->and($result->totalCount)->toBe(0)
        ->and($result->providerName)->toBe('federation.test-server');
});

test('search returns empty result on connection exception', function () {
    Http::fake([
        'https://remote.example.com/api' => function () {
            throw new ConnectionException('Connection refused');
        },
    ]);

    $provider = new FederationProvider(
        endpointUrl: 'https://remote.example.com/api',
        authToken: 'secret-token',
        serverName: 'test-server',
    );

    $query = new SearchQuery(query: 'test');
    $result = $provider->search($query);

    expect($result->items)->toBeEmpty()
        ->and($result->providerName)->toBe('federation.test-server');
});
