<div class="space-y-6">
    <flux:card>
        <form wire:submit="search" class="space-y-4">
            <flux:heading size="lg">Test Retrieval Engine</flux:heading>
            <flux:text>Simulate a request from an AI agent to see how the system plans and retrieves information.</flux:text>

            <div class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <flux:field>
                        <flux:label>Search Query</flux:label>
                        <flux:input wire:model="query" placeholder="Ask anything..." p-4 text-lg />
                    </flux:field>
                </div>
                <div class="w-full md:w-48">
                    <flux:field>
                        <flux:label>Namespace</flux:label>
                        <flux:select wire:model="namespace">
                            <flux:select.option value="">All Namespaces</flux:select.option>
                            @foreach($namespaces as $ns)
                                <flux:select.option value="{{ $ns }}">{{ $ns }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                </div>
                <div class="w-full md:w-48">
                    <flux:field>
                        <flux:label>Search Type</flux:label>
                        <flux:select wire:model="searchType">
                            <flux:select.option value="hybrid">Hybrid (Auto)</flux:select.option>
                            <flux:select.option value="semantic">Semantic Only</flux:select.option>
                            <flux:select.option value="structured">Structured Only</flux:select.option>
                        </flux:select>
                    </flux:field>
                </div>
                <div class="flex items-end">
                    <flux:button type="submit" variant="primary" icon="magnifying-glass" :loading="$isSearching" class="w-full md:w-auto">
                        Execute Search
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:card>

    @if ($plan && $result)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Execution Plan (Reasoning) -->
            <div class="lg:col-span-1 space-y-4">
                <div class="flex justify-between items-center">
                    <flux:heading size="md">Reasoning</flux:heading>
                    <flux:badge color="gray" size="sm">Strategy: {{ $plan->strategy }}</flux:badge>
                </div>
                
                <div class="space-y-3">
                    @forelse($plan->steps as $index => $step)
                        <div class="relative pl-8">
                            <!-- Connector Line -->
                            @if(!$loop->last)
                                <div class="absolute left-[11px] top-6 bottom-[-12px] w-0.5 bg-zinc-200 dark:bg-zinc-700"></div>
                            @endif
                            
                            <!-- Step Number Circle -->
                            <div class="absolute left-0 top-0 size-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-[10px] font-bold z-10">
                                {{ $index + 1 }}
                            </div>
                            
                            <flux:card class="!p-3 bg-white dark:bg-zinc-800 shadow-sm border-zinc-200 dark:border-zinc-700">
                                <div class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">{{ str($step->providerClass)->afterLast('\\') }}</div>
                                <div class="flex items-center gap-2 mt-1">
                                    <flux:badge size="xs" variant="subtle" color="blue">{{ strtoupper($step->operation) }}</flux:badge>
                                    <span class="text-[10px] text-zinc-400 dark:text-zinc-500">Priority {{ $step->priority }}</span>
                                </div>
                                <div class="mt-2 text-[10px] bg-zinc-50 dark:bg-zinc-900 p-1.5 rounded border border-zinc-100 dark:border-zinc-800 font-mono text-zinc-500 dark:text-zinc-400 truncate">
                                    {{ json_encode($step->parameters) }}
                                </div>
                            </flux:card>
                        </div>
                    @empty
                        <flux:text>No providers were identified for this query.</flux:text>
                    @endforelse
                </div>

                <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-lg">
                    <div class="text-xs text-blue-800 dark:text-blue-300 font-medium mb-1">Performance Insight</div>
                    <div class="text-xs text-blue-600 dark:text-blue-400">
                        Search executed across {{ count($plan->steps) }} providers in 
                        <span class="font-bold">{{ round($latency, 2) }}ms</span> using parallel execution.
                    </div>
                </div>
            </div>

            <!-- Fused Results (Evidence) -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex justify-between items-center">
                    <flux:heading size="md">Evidence</flux:heading>
                    <flux:badge color="green" size="sm">{{ $result->totalCount }} Results</flux:badge>
                </div>

                <div class="space-y-4">
                    @forelse($result->items as $item)
                        <flux:card class="hover:border-blue-300 dark:hover:border-blue-700 transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $item['title'] ?? 'Untitled Chunk' }}</div>
                                <flux:badge size="sm" variant="subtle" color="blue">Score: {{ round($item['score'] ?? 0, 4) }}</flux:badge>
                            </div>
                            
                            <div class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed line-clamp-4">
                                {{ $item['content'] }}
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2 items-center">
                                <flux:badge size="xs" variant="outline" color="gray">
                                    {{ $item['metadata']['provider'] ?? 'unknown' }}
                                </flux:badge>
                                @if(isset($item['metadata']['namespace']))
                                    <flux:badge size="xs" variant="outline" color="purple">
                                        {{ $item['metadata']['namespace'] }}
                                    </flux:badge>
                                @endif
                                @if(isset($item['metadata']['source']))
                                    <span class="text-xs text-gray-400 italic">{{ $item['metadata']['source'] }}</span>
                                @endif
                            </div>
                        </flux:card>
                    @empty
                        <div class="py-12 flex flex-col items-center justify-center bg-zinc-50 dark:bg-zinc-900/50 rounded-xl border-2 border-dashed border-zinc-200 dark:border-zinc-800">
                            <flux:icon icon="magnifying-glass" class="size-12 text-zinc-300 dark:text-zinc-700 mb-4" />
                            <flux:heading size="lg" class="text-zinc-400 dark:text-zinc-500">No Evidence Found</flux:heading>
                            <flux:text class="text-zinc-400 dark:text-zinc-500">The retrieval engine returned zero results for this query.</flux:text>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @else
        <div class="py-24 flex flex-col items-center justify-center bg-zinc-50 dark:bg-zinc-900/50 rounded-2xl border-2 border-dashed border-zinc-200 dark:border-zinc-800">
            <flux:icon icon="rocket-launch" class="size-16 text-zinc-200 dark:text-zinc-800 mb-6" />
            <flux:heading size="xl" class="text-zinc-400 dark:text-zinc-500">Ready for Launch</flux:heading>
            <flux:text class="text-zinc-400 dark:text-zinc-500 text-lg">Enter a query above to see the Knowledge Server in action.</flux:text>
        </div>
    @endif
</div>
