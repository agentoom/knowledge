<div>
    <div class="flex items-center justify-between mb-6">
        <flux:heading size="xl">Jobs & Queues</flux:heading>
        <div class="flex items-center gap-3">
            <flux:button wire:click="refreshStats" size="sm" icon="arrow-path">
                Refresh
            </flux:button>
            @if ($horizonRunning)
                <flux:badge color="emerald" size="sm">Horizon Active</flux:badge>
            @else
                <flux:badge color="zinc" size="sm">Horizon Inactive</flux:badge>
            @endif
        </div>
    </div>

    @if ($horizonRunning)
        <!-- Overview Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
            <flux:card class="!p-0 overflow-hidden">
                <div class="p-5">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Recent Jobs</p>
                    <p class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $recentJobs }}</p>
                </div>
                <div class="h-1 w-full bg-blue-500"></div>
            </flux:card>

            <flux:card class="!p-0 overflow-hidden">
                <div class="p-5">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Failed Jobs</p>
                    <p class="text-2xl font-semibold {{ $failedJobs > 0 ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">{{ $failedJobs }}</p>
                </div>
                <div class="h-1 w-full {{ $failedJobs > 0 ? 'bg-red-500' : 'bg-emerald-500' }}"></div>
            </flux:card>

            <flux:card class="!p-0 overflow-hidden">
                <div class="p-5">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Total Pending</p>
                    <p class="text-2xl font-semibold text-zinc-900 dark:text-white">
                        {{ collect($queueStats)->sum('pending') }}
                    </p>
                </div>
                <div class="h-1 w-full bg-amber-500"></div>
            </flux:card>
        </div>

        <!-- Queue Stats Table -->
        <flux:card class="mb-8">
            <flux:heading size="lg" class="mb-4">Queue Workload</flux:heading>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="text-left py-3 px-4 font-medium text-zinc-500">Queue</th>
                            <th class="text-right py-3 px-4 font-medium text-zinc-500">Pending</th>
                            <th class="text-right py-3 px-4 font-medium text-zinc-500">Processed</th>
                            <th class="text-right py-3 px-4 font-medium text-zinc-500">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($queueStats as $queue => $data)
                            <tr class="border-b border-zinc-100 dark:border-zinc-800">
                                <td class="py-3 px-4">
                                    <span class="font-mono text-xs bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 rounded">{{ $queue }}</span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <span class="{{ $data['pending'] > 0 ? 'text-amber-600 font-medium' : 'text-zinc-500' }}">
                                        {{ $data['pending'] }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right text-zinc-500">
                                    {{ number_format($data['processed']) }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    @if ($data['pending'] > 10)
                                        <flux:badge color="amber" size="sm">Busy</flux:badge>
                                    @elseif ($data['pending'] > 0)
                                        <flux:badge color="emerald" size="sm">Active</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">Idle</flux:badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </flux:card>

        <!-- Horizon Dashboard Link -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">Horizon Dashboard</flux:heading>
                    <p class="text-sm text-zinc-500 mt-1">Full queue monitoring, metrics, and job details are available in the Horizon dashboard.</p>
                </div>
                <flux:button :href="$horizonUrl" variant="primary" icon="arrow-top-right-on-square" target="_blank">
                    Open Horizon
                </flux:button>
            </div>
        </flux:card>
    @else
        <flux:card>
            <div class="text-center py-8">
                <flux:icon name="server" class="h-12 w-12 mx-auto text-zinc-400 mb-4" />
                <flux:heading size="lg" class="mb-2">Horizon is not running</flux:heading>
                <p class="text-zinc-500 mb-6 max-w-md mx-auto">
                    Start Horizon to begin processing queued jobs. Run <code class="text-sm bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">vendor/bin/sail artisan horizon</code> in your terminal.
                </p>
                <flux:button wire:click="refreshStats" size="sm" icon="arrow-path">
                    Check Again
                </flux:button>
            </div>
        </flux:card>
    @endif
</div>
