<?php

use App\Knowledge\Models\MetadataRegistry;
use App\Planning\Services\QueryPlanner;
use App\Retrieval\Models\SearchQuery;

test('planner builds execution plan from registry', function () {
    MetadataRegistry::create([
        'payload' => [
            'providers' => [
                [
                    'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
                    'namespace' => 'filesystem',
                    'capabilities' => ['search', 'list_resources'],
                    'resources' => ['documents'],
                    'fields' => ['filename', 'content'],
                ],
                [
                    'class' => 'App\\Providers\\Sql\\SqlProvider',
                    'namespace' => 'sql',
                    'capabilities' => ['search', 'schema_query'],
                    'resources' => ['users'],
                    'fields' => ['*'],
                ],
            ],
            'schemas' => [],
            'resources' => [],
            'relationships' => [],
            'namespaces' => ['filesystem', 'sql'],
            'capabilities' => ['search', 'list_resources', 'schema_query'],
        ],
        'version' => 1,
        'checksum' => 'abc123',
        'built_at' => now(),
    ]);

    $planner = app(QueryPlanner::class);
    $query = new SearchQuery(query: 'test');
    $plan = $planner->plan($query);

    expect($plan->steps)->toHaveCount(2)
        ->and($plan->steps[0]->providerClass)->toBe('App\\Providers\\Filesystem\\FilesystemProvider')
        ->and($plan->steps[1]->providerClass)->toBe('App\\Providers\\Sql\\SqlProvider')
        ->and($plan->strategy)->toBe('federation');
});

test('planner filters by namespace', function () {
    MetadataRegistry::create([
        'payload' => [
            'providers' => [
                [
                    'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
                    'namespace' => 'filesystem',
                    'capabilities' => ['search'],
                    'resources' => ['documents'],
                    'fields' => ['filename', 'content'],
                ],
                [
                    'class' => 'App\\Providers\\Sql\\SqlProvider',
                    'namespace' => 'sql',
                    'capabilities' => ['search'],
                    'resources' => ['users'],
                    'fields' => ['*'],
                ],
            ],
            'schemas' => [],
            'resources' => [],
            'relationships' => [],
            'namespaces' => ['filesystem', 'sql'],
            'capabilities' => ['search'],
        ],
        'version' => 1,
        'checksum' => 'abc123',
        'built_at' => now(),
    ]);

    $planner = app(QueryPlanner::class);
    $query = new SearchQuery(query: 'test', namespace: 'sql');
    $plan = $planner->plan($query);

    expect($plan->steps)->toHaveCount(1)
        ->and($plan->steps[0]->providerClass)->toBe('App\\Providers\\Sql\\SqlProvider');
});

test('planner handles empty registry', function () {
    $planner = app(QueryPlanner::class);
    $query = new SearchQuery(query: 'test');
    $plan = $planner->plan($query);

    expect($plan->steps)->toBeEmpty()
        ->and($plan->strategy)->toBe('default');
});
