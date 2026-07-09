<div>
    <flux:heading size="xl" class="mb-6">Query Planner Settings</flux:heading>

    <flux:card class="mb-4">
        <flux:heading size="lg" class="mb-3">Current Strategy</flux:heading>
        <div class="flex items-center gap-2">
            <flux:badge color="blue" size="lg">{{ $strategyName }}</flux:badge>
        </div>
        <p class="text-sm text-gray-500 mt-2">
            The default planner queries the metadata registry to discover available providers and builds
            dynamic execution plans based on the search query's namespace filter and search type.
        </p>
    </flux:card>

    <flux:card class="mb-4">
        <flux:heading size="lg" class="mb-3">Available Strategies</flux:heading>
        <div class="space-y-2">
            @foreach ($availableStrategies as $strategy)
                <div class="flex items-center gap-2">
                    <flux:badge>{{ $strategy }}</flux:badge>
                </div>
            @endforeach
        </div>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-3">Search Pipeline</flux:heading>
        <p class="text-sm text-gray-600">
            The search pipeline consists of: Query Planning (determines which providers to query)
            → Retrieval Engine (executes searches in parallel) → Result Fusion (merges and ranks results).
        </p>
    </flux:card>
</div>
