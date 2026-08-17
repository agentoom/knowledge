<div>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Activity Log</flux:heading>
    </div>

    {{-- Filter Bar --}}
    <div class="flex items-center gap-4 mb-6">
        <div class="flex-1 max-w-md">
            <flux:input icon="magnifying-glass" placeholder="Filter by actor name…" wire:model.live.debounce.300ms="filterActor" />
        </div>
        <flux:button icon="funnel" variant="outline" wire:click="$toggle('showFilters')">
            Filters
            @if ($filterAction || $filterActor)
                <flux:badge size="sm" color="blue" class="ml-1">•</flux:badge>
            @endif
        </flux:button>
        @if ($filterAction || $filterActor)
            <flux:button icon="x-mark" variant="subtle" wire:click="resetFilters" size="sm">Clear</flux:button>
        @endif
    </div>

    {{-- Filter Panel --}}
    @if ($showFilters)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 p-4 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-zinc-200 dark:border-zinc-800">
            <flux:field>
                <flux:label>Action</flux:label>
                <flux:select wire:model.live="filterAction">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}">{{ $action }}</option>
                    @endforeach
                </flux:select>
            </flux:field>
            <flux:field>
                <flux:label>Actor</flux:label>
                <flux:input wire:model.live.debounce.300ms="filterActor" placeholder="Name, or type “system” for CLI/queue activity" />
            </flux:field>
        </div>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Time</flux:table.column>
            <flux:table.column>Actor</flux:table.column>
            <flux:table.column>Action</flux:table.column>
            <flux:table.column>Subject</flux:table.column>
            <flux:table.column>IP</flux:table.column>
            <flux:table.column>Properties</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
        @forelse ($logs as $log)
            <flux:table.row :key="$log->id">
                <flux:table.cell class="whitespace-nowrap text-sm">
                    {{ $log->created_at->format('Y-m-d H:i:s') }}
                </flux:table.cell>
                <flux:table.cell class="whitespace-nowrap">
                    @if ($log->user)
                        <flux:badge size="sm" color="blue">{{ $log->user->name }}</flux:badge>
                    @else
                        <flux:badge size="sm" color="gray">System</flux:badge>
                    @endif
                </flux:table.cell>
                <flux:table.cell class="whitespace-nowrap font-mono text-xs">
                    {{ $log->action }}
                </flux:table.cell>
                <flux:table.cell class="text-sm max-w-xs truncate">
                    @if ($log->subject)
                        {{ str($log->subject_type)->afterLast('\\') }}: {{ $log->subject->name ?? $log->subject->filename ?? $log->subject_id }}
                    @elseif ($log->subject_type)
                        {{ str($log->subject_type)->afterLast('\\') }} #{{ $log->subject_id }}
                    @else
                        <span class="text-zinc-400 dark:text-zinc-500">—</span>
                    @endif
                </flux:table.cell>
                <flux:table.cell class="text-sm whitespace-nowrap">
                    {{ $log->ip_address ?? '—' }}
                </flux:table.cell>
                <flux:table.cell class="max-w-xs">
                    @if (!empty($log->properties))
                        <details class="text-xs">
                            <summary class="cursor-pointer text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300">
                                {{ count($log->properties) }} field{{ count($log->properties) !== 1 ? 's' : '' }}
                            </summary>
                            <pre class="mt-1 p-2 bg-zinc-50 dark:bg-zinc-900/50 rounded border border-zinc-200 dark:border-zinc-800 overflow-x-auto max-h-48 text-[11px] text-zinc-700 dark:text-zinc-300">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    @else
                        <span class="text-zinc-400 dark:text-zinc-500">—</span>
                    @endif
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="6" class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                    No activity log entries found matching your criteria.
                </flux:table.cell>
            </flux:table.row>
        @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>
</div>
