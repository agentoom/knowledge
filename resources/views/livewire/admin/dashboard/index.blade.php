<div>
    <flux:heading size="xl" class="mb-2">Dashboard</flux:heading>
    <flux:text class="mb-8 text-gray-500 dark:text-gray-400">Overview of your knowledge server.</flux:text>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        <!-- Knowledge Sources Card -->
        <flux:card class="relative overflow-hidden !p-0">
            <div class="p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                        <flux:icon name="document-text" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $totalSources }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Sources</p>
                    </div>
                </div>
            </div>
            <div class="h-1 w-full bg-blue-500"></div>
        </flux:card>

        <!-- Active Providers Card -->
        <flux:card class="relative overflow-hidden !p-0">
            <div class="p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                        <flux:icon name="squares-plus" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $activeProviders }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Providers</p>
                    </div>
                </div>
            </div>
            <div class="h-1 w-full bg-emerald-500"></div>
        </flux:card>

        <!-- Documents Card -->
        <flux:card class="relative overflow-hidden !p-0">
            <div class="p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
                        <flux:icon name="folder" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $totalDocuments }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Documents</p>
                    </div>
                </div>
            </div>
            <div class="h-1 w-full bg-violet-500"></div>
        </flux:card>

        <!-- Vector Store Card -->
        <flux:card class="relative overflow-hidden !p-0">
            <div class="p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                        <flux:icon name="server" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <div class="mb-0.5">
                            <flux:badge size="sm" :color="$vectorStoreHealthy ? 'emerald' : 'red'" class="!text-[10px] px-1.5 py-0">
                                {{ $vectorStoreHealthy ? 'Healthy' : 'Unhealthy' }}
                            </flux:badge>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Vector Store</p>
                    </div>
                </div>
            </div>
            <div class="h-1 w-full {{ $vectorStoreHealthy ? 'bg-emerald-500' : 'bg-red-500' }}"></div>
        </flux:card>

        <!-- Queue Status Card -->
        <flux:card class="relative overflow-hidden !p-0">
            <div class="p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400">
                        <flux:icon name="queue-list" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0">
                        <div class="mb-0.5">
                            <flux:badge size="sm" :color="$queueStatus === 'active' ? 'emerald' : 'red'" class="!text-[10px] px-1.5 py-0">
                                {{ ucfirst($queueStatus) }}
                            </flux:badge>
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Queue Workers</p>
                    </div>
                </div>
            </div>
            <div class="h-1 w-full {{ $queueStatus === 'active' ? 'bg-emerald-500' : 'bg-red-500' }}"></div>
        </flux:card>
    </div>

    <!-- Second row: additional stats + activity -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="lg:col-span-2 space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4">System Details</flux:heading>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-zinc-500">Total Chunks</p>
                        <p class="mt-1 text-xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totalChunks) }}</p>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-zinc-500">Vector Index Size</p>
                        <p class="mt-1 text-xl font-semibold text-zinc-900 dark:text-white">{{ number_format($vectorStoreStats['document_count'] ?? 0) }}</p>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-zinc-500">Last Dashboard Update</p>
                        <p class="mt-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $timestamp }}</p>
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <div class="flex justify-between items-center mb-4">
                    <flux:heading size="lg">Recent Search Activity</flux:heading>
                    <a href="{{ route('admin.retrieval-logs.index') }}" wire:navigate class="text-xs text-blue-600 hover:underline">View all logs</a>
                </div>
                
                <div class="space-y-3">
                    @forelse($recentLogs as $log)
                        <div class="flex items-center justify-between p-3 bg-zinc-50 rounded-lg dark:bg-zinc-800">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ $log['query'] }}</div>
                                <div class="text-[10px] text-zinc-500">{{ \Carbon\Carbon::parse($log['created_at'])->diffForHumans() }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge size="sm" variant="subtle">{{ $log['latency_ms'] }}ms</flux:badge>
                                <flux:badge size="sm" variant="subtle" color="blue">{{ count($log['fused_results']) }} results</flux:badge>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-zinc-500 text-sm">No recent search activity.</div>
                    @endforelse
                </div>
            </flux:card>
        </div>

        <div class="space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>
                <div class="space-y-2">
                    <a href="{{ route('admin.playground') }}" wire:navigate class="flex items-center gap-2 rounded-lg p-2 text-sm font-medium text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-500/10 transition-colors">
                        <flux:icon name="rocket-launch" class="h-4 w-4" />
                        Open Search Playground
                    </a>
                    <flux:separator variant="subtle" />
                    <a href="{{ route('admin.knowledge-sources.index') }}" wire:navigate class="flex items-center gap-2 rounded-lg p-2 text-sm text-zinc-600 hover:bg-zinc-50 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                        <flux:icon name="document-text" class="h-4 w-4" />
                        Manage Sources
                    </a>
                    <a href="{{ route('admin.providers.index') }}" wire:navigate class="flex items-center gap-2 rounded-lg p-2 text-sm text-zinc-600 hover:bg-zinc-50 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                        <flux:icon name="squares-plus" class="h-4 w-4" />
                        View Providers
                    </a>
                    <a href="{{ route('admin.documents.index') }}" wire:navigate class="flex items-center gap-2 rounded-lg p-2 text-sm text-zinc-600 hover:bg-zinc-50 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                        <flux:icon name="folder" class="h-4 w-4" />
                        Browse Documents
                    </a>
                    <a href="{{ route('admin.vector-store.settings') }}" wire:navigate class="flex items-center gap-2 rounded-lg p-2 text-sm text-zinc-600 hover:bg-zinc-50 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                        <flux:icon name="server" class="h-4 w-4" />
                        Vector Store Settings
                    </a>
                </div>
            </flux:card>
        </div>
    </div>
</div>
