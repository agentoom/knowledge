<div>
    <div class="flex justify-between items-center mb-2">
        <flux:heading size="xl">System Health</flux:heading>
        <flux:button icon="arrow-path" wire:click="refresh" size="sm" variant="outline">
            Refresh
        </flux:button>
    </div>
    <flux:text class="mb-8 text-gray-500 dark:text-gray-400">
        System diagnostics and environment overview.
    </flux:text>

    {{-- Status Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach ($checks as $name => $check)
            <flux:card class="relative overflow-hidden !p-0">
                <div class="p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl
                            {{ match($check['status']) {
                                'ok' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
                                'warning' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
                                'error' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400',
                                default => 'bg-zinc-50 text-zinc-600 dark:bg-zinc-500/10 dark:text-zinc-400',
                            } }}">
                            <flux:icon name="{{ $check['icon'] }}" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white capitalize">
                                {{ str_replace('_', ' ', $name) }}
                            </p>
                            <div class="mt-0.5">
                                <flux:badge
                                    size="sm"
                                    :color="match($check['status']) {
                                        'ok' => 'emerald',
                                        'warning' => 'amber',
                                        'error' => 'red',
                                        default => 'zinc',
                                    }"
                                >
                                    {{ match($check['status']) {
                                        'ok' => 'Healthy',
                                        'warning' => 'Warning',
                                        'error' => 'Error',
                                        default => 'Unknown',
                                    } }}
                                </flux:badge>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="h-1 w-full
                    {{ match($check['status']) {
                        'ok' => 'bg-emerald-500',
                        'warning' => 'bg-amber-500',
                        'error' => 'bg-red-500',
                        default => 'bg-zinc-400',
                    } }}"></div>
            </flux:card>
        @endforeach
    </div>

    {{-- Overall health banner --}}
    @php $overall = $this->getOverallStatus(); @endphp
    <flux:callout
        :color="match($overall) { 'ok' => 'emerald', 'warning' => 'amber', 'error' => 'red', default => 'zinc' }"
        :icon="match($overall) { 'ok' => 'check-circle', 'warning' => 'exclamation-triangle', 'error' => 'x-circle', default => 'question-mark-circle' }"
        class="mb-8"
    >
        <flux:heading size="sm" class="mb-1">
            Overall Status: {{ match($overall) { 'ok' => 'All Systems Operational', 'warning' => 'Degraded Performance', 'error' => 'System Issues Detected', default => 'Unknown' } }}
        </flux:heading>
        <p class="text-sm">
            {{ $this->getOkCount() }} of {{ count($checks) }} checks passing
            &middot; Last refreshed: {{ $timestamp }}
        </p>
    </flux:callout>

    {{-- Second row: details + stats --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="lg:col-span-2 space-y-6">
            {{-- Check Details --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">Check Details</flux:heading>
                <div class="space-y-3">
                    @foreach ($checks as $name => $check)
                        <div class="flex items-start gap-3 p-3 rounded-lg
                            {{ match($check['status']) {
                                'ok' => 'bg-emerald-50/50 dark:bg-emerald-500/5',
                                'warning' => 'bg-amber-50/50 dark:bg-amber-500/5',
                                'error' => 'bg-red-50/50 dark:bg-red-500/5',
                                default => 'bg-zinc-50 dark:bg-zinc-800',
                            } }}">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg
                                {{ match($check['status']) {
                                    'ok' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400',
                                    'warning' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400',
                                    'error' => 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400',
                                    default => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-500/20 dark:text-zinc-400',
                                } }}">
                                <flux:icon name="{{ $check['icon'] }}" class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-white capitalize">
                                        {{ str_replace('_', ' ', $name) }}
                                    </p>
                                    <flux:badge size="sm" :color="match($check['status']) { 'ok' => 'emerald', 'warning' => 'amber', 'error' => 'red', default => 'zinc' }">
                                        {{ $check['status'] }}
                                    </flux:badge>
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 truncate">
                                    {{ $check['message'] }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </flux:card>

            {{-- System Overview Stats --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">System Overview</flux:heading>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-zinc-500">Knowledge Sources</p>
                        <p class="mt-1 text-xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totalSources) }}</p>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-zinc-500">Documents</p>
                        <p class="mt-1 text-xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totalDocuments) }}</p>
                    </div>
                    <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-zinc-500">Chunks</p>
                        <p class="mt-1 text-xl font-semibold text-zinc-900 dark:text-white">{{ number_format($totalChunks) }}</p>
                    </div>
                </div>
            </flux:card>
        </div>

        {{-- Sidebar: Environment + Quick Actions --}}
        <div class="space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4">Environment</flux:heading>
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Laravel</span>
                        <flux:badge size="sm" variant="subtle">{{ app()->version() }}</flux:badge>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">PHP</span>
                        <flux:badge size="sm" variant="subtle">{{ phpversion() }}</flux:badge>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Environment</span>
                        <flux:badge size="sm" :color="$appEnv === 'production' ? 'red' : 'blue'">{{ $appEnv }}</flux:badge>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Debug Mode</span>
                        <flux:badge size="sm" :color="$appDebug === 'true' ? 'amber' : 'emerald'">{{ $appDebug === 'true' ? 'ON' : 'OFF' }}</flux:badge>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Queue Driver</span>
                        <flux:badge size="sm" variant="subtle">{{ $queueDriver }}</flux:badge>
                    </div>
                    <div class="flex items-center justify-between p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400">Cache Driver</span>
                        <flux:badge size="sm" variant="subtle">{{ $cacheDriver }}</flux:badge>
                    </div>
                </div>
            </flux:card>

            <flux:card>
                <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>
                <div class="space-y-1">
                    <a href="{{ route('admin.dashboard') }}" wire:navigate class="flex items-center gap-2 rounded-lg p-2 text-sm font-medium text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-500/10 transition-colors">
                        <flux:icon name="chart-bar" class="h-4 w-4" />
                        Admin Dashboard
                    </a>
                    <flux:separator variant="subtle" />
                    <a href="{{ route('admin.jobs.index') }}" wire:navigate class="flex items-center gap-2 rounded-lg p-2 text-sm text-zinc-600 hover:bg-zinc-50 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                        <flux:icon name="queue-list" class="h-4 w-4" />
                        Jobs &amp; Queues
                    </a>
                    <a href="{{ route('admin.settings') }}" wire:navigate class="flex items-center gap-2 rounded-lg p-2 text-sm text-zinc-600 hover:bg-zinc-50 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                        <flux:icon name="cog" class="h-4 w-4" />
                        Settings
                    </a>
                </div>
            </flux:card>
        </div>
    </div>
</div>
