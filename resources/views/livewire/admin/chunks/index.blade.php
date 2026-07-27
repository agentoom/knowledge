<div>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Chunks</flux:heading>
    </div>

    @if (session()->has('status'))
        <flux:callout color="green" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    {{-- Search & Filter Bar --}}
    <div class="flex items-center gap-4 mb-6">
        <div class="flex-1 max-w-md">
            <flux:input icon="magnifying-glass" placeholder="Search by document name…" wire:model.live.debounce.300ms="search" />
        </div>
        <flux:button icon="funnel" variant="outline" wire:click="$toggle('showFilters')">
            Filters
            @if ($filterSource || $filterIndexed)
                <flux:badge size="sm" color="blue" class="ml-1">•</flux:badge>
            @endif
        </flux:button>
        @if ($search || $filterSource || $filterIndexed)
            <flux:button icon="x-mark" variant="subtle" wire:click="resetFilters" size="sm">Clear</flux:button>
        @endif
    </div>

    {{-- Filter Panel --}}
    @if ($showFilters)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 p-4 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-zinc-200 dark:border-zinc-800">
            <flux:field>
                <flux:label>Knowledge Source</flux:label>
                <flux:select wire:model.live="filterSource">
                    <option value="">All sources</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source->id }}">{{ $source->name }}</option>
                    @endforeach
                </flux:select>
            </flux:field>
            <flux:field>
                <flux:label>Indexing Status</flux:label>
                <flux:select wire:model.live="filterIndexed">
                    <option value="">All</option>
                    <option value="yes">Indexed</option>
                    <option value="no">Pending</option>
                </flux:select>
            </flux:field>
        </div>
    @endif

    @if ($chunks->isEmpty())
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <div class="rounded-full bg-zinc-100 dark:bg-zinc-800 p-4 mb-4">
                <flux:icon name="squares-plus" class="size-10 text-zinc-300 dark:text-zinc-600" />
            </div>
            <h3 class="text-lg font-semibold text-zinc-600 dark:text-zinc-400 mb-1">No chunks found</h3>
            <p class="text-sm text-zinc-500 dark:text-zinc-500 max-w-md mb-6">
                Run the document pipeline to parse and chunk documents into searchable segments.
            </p>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'filename'" :direction="$sortDirection" wire:click="sortBy('filename')">Document</flux:table.column>
                <flux:table.column>Source</flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'sequence'" :direction="$sortDirection" wire:click="sortBy('sequence')">Sequence</flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'token_count'" :direction="$sortDirection" wire:click="sortBy('token_count')">Tokens</flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'indexed_at'" :direction="$sortDirection" wire:click="sortBy('indexed_at')">Indexed</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
            @foreach ($chunks as $chunk)
                <flux:table.row :key="$chunk->id">
                    <flux:table.cell class="max-w-[200px] truncate">
                        {{ $chunk->document?->filename ?? 'Unknown' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge>{{ $chunk->document?->knowledgeSource?->name ?? 'Unknown' }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>#{{ $chunk->sequence }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$chunk->token_count > 1000 ? 'orange' : ($chunk->token_count > 500 ? 'yellow' : 'green')" size="sm">
                            {{ number_format($chunk->token_count) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$chunk->indexed_at ? 'green' : 'yellow'">
                            {{ $chunk->indexed_at ? 'Indexed' : 'Pending' }}
                        </flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $chunks->links() }}
        </div>
    @endif
</div>
