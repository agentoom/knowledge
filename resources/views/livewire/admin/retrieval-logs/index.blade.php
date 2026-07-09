<div>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Retrieval Logs</flux:heading>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Time</flux:table.column>
            <flux:table.column>Query</flux:table.column>
            <flux:table.column>Namespace</flux:table.column>
            <flux:table.column>Latency</flux:table.column>
            <flux:table.column>Sources</flux:table.column>
            <flux:table.column>Results</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
        @foreach ($logs as $log)
            <flux:table.row :key="$log->id">
                <flux:table.cell class="whitespace-nowrap text-sm">
                    {{ $log->created_at->diffForHumans() }}
                </flux:table.cell>
                <flux:table.cell class="max-w-xs truncate font-medium">
                    {{ $log->query }}
                </flux:table.cell>
                <flux:table.cell>
                    <flux:badge size="sm">{{ $log->metadata['namespace'] ?? 'global' }}</flux:badge>
                </flux:table.cell>
                <flux:table.cell>
                    <flux:badge size="sm" :color="$log->latency_ms > 1000 ? 'orange' : 'green'">
                        {{ $log->latency_ms }}ms
                    </flux:badge>
                </flux:table.cell>
                <flux:table.cell class="text-sm">
                    {{ count($log->execution_plan) }}
                </flux:table.cell>
                <flux:table.cell class="text-sm">
                    {{ count($log->fused_results) }}
                </flux:table.cell>
                <flux:table.cell>
                    <flux:button variant="subtle" size="sm" wire:click="viewDetails({{ $log->id }})">
                        Details
                    </flux:button>
                </flux:table.cell>
            </flux:table.row>
        @endforeach
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>

    <flux:modal wire:model="showDetailModal" class="w-full max-w-4xl">
        @if($selectedLog)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Log Details #{{ $selectedLog->id }}</flux:heading>
                    <div class="text-sm text-gray-500">{{ $selectedLog->created_at->format('Y-m-d H:i:s') }}</div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Query</flux:label>
                        <div class="p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-zinc-200 dark:border-zinc-800 text-sm font-medium">{{ $selectedLog->query }}</div>
                    </flux:field>
                    <div class="grid grid-cols-2 gap-4">
                        <flux:field>
                            <flux:label>Latency</flux:label>
                            <div class="p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-zinc-200 dark:border-zinc-800 text-sm">{{ $selectedLog->latency_ms }}ms</div>
                        </flux:field>
                        <flux:field>
                            <flux:label>Strategy</flux:label>
                            <div class="p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-zinc-200 dark:border-zinc-800 text-sm capitalize">{{ $selectedLog->metadata['strategy'] ?? 'default' }}</div>
                        </flux:field>
                    </div>
                </div>

                <div>
                    <flux:heading size="md" class="mb-2">Execution Plan</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        @foreach($selectedLog->execution_plan as $step)
                            <div class="p-3 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-zinc-200 dark:border-zinc-800 text-xs">
                                <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ str($step['providerClass'])->afterLast('\\') }}</div>
                                <div class="text-zinc-500 dark:text-zinc-400 mt-1">
                                    <span class="bg-white dark:bg-zinc-800 px-1.5 py-0.5 rounded border border-zinc-200 dark:border-zinc-700">{{ strtoupper($step['operation']) }}</span>
                                    <span class="ml-2">Priority: {{ $step['priority'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <flux:heading size="md" class="mb-2">Fused Results ({{ count($selectedLog->fused_results) }})</flux:heading>
                    <div class="max-h-80 overflow-y-auto space-y-3 pr-2">
                        @forelse($selectedLog->fused_results as $item)
                            <div class="p-4 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-zinc-200 dark:border-zinc-800 text-sm">
                                <div class="font-bold text-zinc-900 dark:text-zinc-100 mb-1">{{ $item['title'] ?? 'Untitled' }}</div>
                                <div class="text-zinc-600 dark:text-zinc-400 leading-relaxed mb-3">{{ str($item['content'] ?? '')->limit(300) }}</div>
                                <div class="flex items-center gap-3">
                                    <flux:badge size="sm" variant="subtle" color="blue">Score: {{ round($item['score'] ?? 0, 4) }}</flux:badge>
                                    <flux:badge size="sm" variant="subtle" color="gray">{{ $item['metadata']['provider'] ?? 'unknown' }}</flux:badge>
                                    @if(isset($item['metadata']['source']))
                                        <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $item['metadata']['source'] }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-8 text-zinc-500 dark:text-zinc-400 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-dashed border-zinc-200 dark:border-zinc-800">
                                No results were found for this query.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t">
                    <flux:button variant="outline" wire:click="$set('showDetailModal', false)">Close</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
