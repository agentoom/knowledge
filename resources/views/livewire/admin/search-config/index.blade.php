<div>
    <form wire:submit="save" @change="$dispatch('settings-dirty')">
        {{-- Pipeline --}}
        <flux:heading size="lg" class="mb-4">Search Pipeline</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <flux:label>Default Planner Strategy</flux:label>
                    <flux:select wire:model="defaultPlannerStrategy">
                        @foreach ($availablePlannerStrategies as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>
                        Determines how the Query Planner discovers providers and routes search requests.
                        <strong>Default (Rule-based):</strong> queries local providers sorted by priority.
                        <strong>Federation (Local + Remote):</strong> includes federated remote servers after local providers.
                    </flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Default Fusion Strategy</flux:label>
                    <flux:select wire:model="defaultFusionStrategy">
                        @foreach ($availableFusionStrategies as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>
                        Algorithm for merging and ranking results from multiple providers.
                        RRF (Reciprocal Rank Fusion) assigns higher scores to items that rank consistently high across providers, then deduplicates the final set.
                    </flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Default Max Results</flux:label>
                    <flux:input type="number" wire:model="defaultMaxResults" min="1" max="100" />
                    <flux:error name="defaultMaxResults" />
                    <flux:description>Maximum number of fused results returned per query (1–100).</flux:description>
                </flux:field>
            </div>
        </flux:card>

        {{-- Chunking --}}
        <flux:heading size="lg" class="mb-4">Chunking</flux:heading>

        <flux:card class="mb-6">
            <div class="space-y-6">
                <flux:field>
                    <flux:label>Default Chunking Strategy</flux:label>
                    <flux:select wire:model="defaultChunkingStrategy">
                        @foreach ($availableChunkingStrategies as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>Algorithm used to split documents into indexable chunks. <strong>Fixed Size</strong> splits at exact character counts. <strong>Markdown</strong> respects heading boundaries. <strong>Recursive</strong> uses nested separators for natural breaks.</flux:description>
                </flux:field>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <flux:field>
                        <flux:label>Chunk Size (characters)</flux:label>
                        <flux:input type="number" wire:model="chunkSize" min="100" max="10000" />
                        <flux:error name="chunkSize" />
                        <flux:description>Target characters per chunk (100–10000).</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Chunk Overlap (characters)</flux:label>
                        <flux:input type="number" wire:model="chunkOverlap" min="0" max="1000" />
                        <flux:error name="chunkOverlap" />
                        <flux:description>Overlapping characters between adjacent chunks (0–1000).</flux:description>
                    </flux:field>
                </div>
            </div>
        </flux:card>

        {{-- Vector Store --}}
        <flux:heading size="lg" class="mb-4">Vector Store</flux:heading>

        <flux:card class="mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">Driver</span>
                    <flux:badge color="blue">{{ $vectorDriver }}</flux:badge>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-zinc-600 dark:text-zinc-300">Health</span>
                    <flux:badge :color="$vectorHealthy ? 'green' : 'red'">
                        {{ $vectorHealthy ? 'Healthy' : 'Unhealthy' }}
                    </flux:badge>
                </div>
            </div>

            @if (!empty($vectorStats))
                <flux:separator class="my-4" />
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    @foreach ($vectorStats as $key => $value)
                        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-800 p-3">
                            <p class="text-[10px] font-medium uppercase tracking-wide text-zinc-500">{{ str_replace('_', ' ', $key) }}</p>
                            <p class="mt-0.5 text-lg font-semibold text-zinc-900 dark:text-white">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            @if (!empty($vectorCapabilities))
                <flux:separator class="my-4" />
                <div>
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-300 mb-2">Capabilities</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($vectorCapabilities as $capability)
                            <flux:badge variant="subtle" color="purple" size="sm">{{ str_replace('_', ' ', $capability) }}</flux:badge>
                        @endforeach
                    </div>
                </div>
            @endif
        </flux:card>

        {{-- Pipeline Flow --}}
        <flux:heading size="lg" class="mb-4">Pipeline Flow</flux:heading>

        <flux:card class="mb-6">
            <div class="flex items-center gap-2 flex-wrap">
                <flux:badge color="blue">1. Query Planning</flux:badge>
                <flux:icon icon="arrow-right" class="size-4 text-zinc-400" />
                <flux:badge color="green">2. Retrieval Engine</flux:badge>
                <flux:icon icon="arrow-right" class="size-4 text-zinc-400" />
                <flux:badge color="purple">3. Result Fusion</flux:badge>
            </div>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-3">
                The Query Planner reads the metadata registry to discover available providers and builds a parallel execution plan.
                The Retrieval Engine queries all matched providers concurrently and passes results to the Fusion strategy,
                which merges and ranks them into a single deduplicated result set.
            </p>
        </flux:card>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Save Settings</flux:button>
        </div>
    </form>
</div>
