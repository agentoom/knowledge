<?php

use App\Knowledge\Models\KnowledgeSource;
use App\Knowledge\Models\Provider;
use App\Livewire\Admin\Search\Playground;
use App\Retrieval\Models\ExecutionPlan;
use App\Retrieval\Models\PlanStep;
use Livewire\Livewire;

it('can perform a search in the playground', function () {
    // Create some test data
    $source = KnowledgeSource::factory()->create([
        'name' => 'Test Source',
        'namespace' => 'test',
    ]);

    Provider::create([
        'knowledge_source_id' => $source->id,
        'name' => 'test-provider',
        'type' => 'filesystem',
        'class' => 'App\\Providers\\Filesystem\\FilesystemProvider',
        'status' => 'active',
    ]);

    Livewire::test(Playground::class)
        ->set('query', 'test')
        ->call('search')
        ->assertHasNoErrors()
        ->assertSet('isSearching', false)
        ->assertViewHas('namespaces')
        ->tap(function ($component) {
            $plan = $component->get('plan');
            expect($plan)->toBeInstanceOf(ExecutionPlan::class);
            if (count($plan->steps) > 0) {
                expect($plan->steps[0])->toBeInstanceOf(PlanStep::class);
            }
        });
});
