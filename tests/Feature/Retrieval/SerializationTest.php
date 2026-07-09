<?php

use App\Knowledge\Services\ProviderManager;
use App\Retrieval\Fusion\ReciprocalRankFusion;
use App\Retrieval\Models\ExecutionPlan;
use App\Retrieval\Models\PlanStep;
use App\Retrieval\Services\RetrievalEngine;
use Laravel\SerializableClosure\SerializableClosure;

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
    $method = $reflection->getMethod('buildCallbacks');
    $method->setAccessible(true);

    $callbacks = $method->invoke($engine, $plan);

    foreach ($callbacks as $callback) {
        $serialized = serialize(new SerializableClosure($callback));
        expect($serialized)->toBeString();
    }
});
