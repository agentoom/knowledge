<?php

use App\Mcp\Servers\KnowledgeServer;
use App\Mcp\Tools\GetSourceSchema;
use App\Mcp\Tools\ListSources;
use App\Mcp\Tools\SearchKnowledge;

test('knowledge server metadata', function () {
    $reflection = new ReflectionClass(KnowledgeServer::class);
    $attributes = $reflection->getAttributes();

    expect($attributes)->toHaveCount(3);
});

test('search knowledge tool is registered', function () {
    $response = KnowledgeServer::tool(SearchKnowledge::class, [
        'query' => 'test',
    ]);

    $response->assertOk();
});

test('list sources tool is registered', function () {
    $response = KnowledgeServer::tool(ListSources::class);

    $response->assertOk();
});

test('get source schema tool is registered', function () {
    $response = KnowledgeServer::tool(GetSourceSchema::class, [
        'source_id' => 'test',
    ]);

    $response->assertHasErrors();
});

test('search knowledge tool returns error when query is empty', function () {
    $response = KnowledgeServer::tool(SearchKnowledge::class, [
        'query' => '',
    ]);

    $response->assertHasErrors();
});

test('search knowledge tool accepts valid parameters', function () {
    $response = KnowledgeServer::tool(SearchKnowledge::class, [
        'query' => 'test query',
    ]);

    $response->assertOk();
});

test('search knowledge tool accepts all optional parameters', function () {
    $response = KnowledgeServer::tool(SearchKnowledge::class, [
        'query' => 'test query',
        'namespace' => 'docs',
        'max_results' => 5,
        'filters' => ['status' => 'active'],
        'search_type' => 'hybrid',
    ]);

    $response->assertOk();
});

test('list sources tool returns sources', function () {
    $response = KnowledgeServer::tool(ListSources::class);

    $response->assertOk();
});

test('list sources tool accepts namespace filter', function () {
    $response = KnowledgeServer::tool(ListSources::class, [
        'namespace' => 'docs',
    ]);

    $response->assertOk();
});

test('get source schema tool returns error when source id is missing', function () {
    $response = KnowledgeServer::tool(GetSourceSchema::class, [
        'source_id' => '',
    ]);

    $response->assertHasErrors();
});

test('get source schema tool returns error for unknown source', function () {
    $response = KnowledgeServer::tool(GetSourceSchema::class, [
        'source_id' => 'nonexistent',
    ]);

    $response->assertHasErrors();
});
