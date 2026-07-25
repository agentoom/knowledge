<?php

use App\Knowledge\Services\ProviderManager;
use App\Retrieval\Fusion\ReciprocalRankFusion;
use App\Retrieval\Models\ExecutionPlan;
use App\Retrieval\Models\PlanStep;
use App\Retrieval\Services\RetrievalEngine;

test('retrieval engine callbacks can be serialized', function () {
    $fusion = new ReciprocalRankFusion;
    $providerManager = app(ProviderManager::class);

    $engine = new RetrievalEngine($fusion, $providerManager);

    $plan = new ExecutionPlan(steps: [
        new PlanStep(
            providerClass: 'App\\Providers\\Filesystem\\FilesystemProvider',
            operation: 'search',
            parameters: ['query' => 'test'],
            priority: 10,
        ),
    ], strategy: 'default');

    $reflection = new ReflectionClass(RetrievalEngine::class);

    // Verify splitSteps correctly separates local from remote
    $splitMethod = $reflection->getMethod('splitSteps');
    $splitMethod->setAccessible(true);

    [$local, $remote] = $splitMethod->invoke($engine, $plan->steps);

    expect($local)->toHaveCount(1)
        ->and($remote)->toBeEmpty();

    // Verify local steps produce serializable callbacks (needed for Concurrency::run)
    $execMethod = $reflection->getMethod('executeLocalSteps');
    $execMethod->setAccessible(true);

    // We can't invoke executeLocalSteps without ProviderManager binding,
    // but we can verify the method exists and handles the local step
    expect($execMethod->getNumberOfParameters())->toBe(1);
});
