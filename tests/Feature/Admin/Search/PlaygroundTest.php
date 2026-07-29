<?php

use App\Knowledge\Models\KnowledgeSource;
use App\Knowledge\Models\Provider;
use App\Livewire\Admin\Search\Playground;
use App\Retrieval\Models\ExecutionPlan;
use App\Retrieval\Models\PlanStep;
use Livewire\Livewire;

it('mutually excludes search types in A/B mode when Side A collides with Side B', function () {
    Livewire::test(Playground::class)
        ->set('abMode', true)
        ->set('searchType', 'semantic')
        ->set('searchTypeB', 'semantic')
        // When B is set to the same as A, A should be bumped
        ->assertSet('searchTypeB', 'semantic')
        ->assertSet('searchType', fn ($v) => $v !== 'semantic');
});

it('mutually excludes search types in A/B mode when Side A changes and collides with B', function () {
    Livewire::test(Playground::class)
        ->set('abMode', true)
        ->set('searchTypeB', 'semantic')
        ->set('searchType', 'semantic')
        // When A is set to the same as B, B should be bumped
        ->assertSet('searchType', 'semantic')
        ->assertSet('searchTypeB', fn ($v) => $v !== 'semantic');
});

it('mutually excludes search types in A/B mode when Side B changes', function () {
    Livewire::test(Playground::class)
        ->set('abMode', true)
        ->set('searchType', 'hybrid')
        ->set('searchTypeB', 'semantic')
        // Both are different — should stay
        ->assertSet('searchType', 'hybrid')
        ->assertSet('searchTypeB', 'semantic')
        // Now set Side B to match Side A
        ->set('searchTypeB', 'hybrid')
        // Side A should bump to something else
        ->assertSet('searchTypeB', 'hybrid')
        ->assertSet('searchType', fn ($v) => $v !== 'hybrid');
});

it('dispatches notify event when Side B is bumped by Side A collision', function () {
    Livewire::test(Playground::class)
        ->set('abMode', true)
        ->set('searchTypeB', 'semantic')
        ->set('searchType', 'semantic')
        ->assertSet('searchType', 'semantic')
        ->assertSet('searchTypeB', fn ($v) => $v !== 'semantic')
        ->assertDispatched('notify');
});

it('dispatches notify event when Side A is bumped by Side B collision', function () {
    Livewire::test(Playground::class)
        ->set('abMode', true)
        ->set('searchType', 'hybrid')
        ->set('searchTypeB', 'semantic')
        ->set('searchTypeB', 'hybrid')
        ->assertSet('searchTypeB', 'hybrid')
        ->assertSet('searchType', fn ($v) => $v !== 'hybrid')
        ->assertDispatched('notify');
});

it('does not exclude when A/B mode is off', function () {
    Livewire::test(Playground::class)
        ->set('abMode', false)
        ->set('searchType', 'hybrid')
        ->assertSet('searchType', 'hybrid');
    // searchTypeB shouldn't change since we're not in AB mode
});

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
