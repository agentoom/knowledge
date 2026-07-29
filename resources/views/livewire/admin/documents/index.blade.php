<div>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Documents</flux:heading>
    </div>

    @if (session()->has('status'))
        <flux:callout color="green" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    {{-- Search & Filter Bar --}}
    <div class="flex items-center gap-4 mb-6">
        <div class="flex-1 max-w-md">
            <flux:input icon="magnifying-glass" placeholder="Search documents…" wire:model.live.debounce.300ms="search" />
        </div>
        <flux:button icon="funnel" variant="outline" wire:click="$toggle('showFilters')">
            Filters
            @if ($filterSource || $filterStatus)
                <flux:badge size="sm" color="blue" class="ml-1">•</flux:badge>
            @endif
        </flux:button>
        @if ($search || $filterSource || $filterStatus)
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
                <flux:label>Status</flux:label>
                <flux:select wire:model.live="filterStatus">
                    <option value="">All statuses</option>
                    <option value="indexed">Indexed</option>
                    <option value="chunked">Chunked</option>
                    <option value="parsed">Parsed</option>
                    <option value="discovered">Discovered</option>
                    <option value="error">Error</option>
                    <option value="duplicate">Duplicate</option>
                </flux:select>
            </flux:field>
        </div>
    @endif

    @if ($documents->isEmpty())
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <div class="rounded-full bg-zinc-100 dark:bg-zinc-800 p-4 mb-4">
                <flux:icon name="document-text" class="size-10 text-zinc-300 dark:text-zinc-600" />
            </div>
            <h3 class="text-lg font-semibold text-zinc-600 dark:text-zinc-400 mb-1">No documents found</h3>
            <p class="text-sm text-zinc-500 dark:text-zinc-500 max-w-md mb-6">
                Run the document pipeline on your knowledge sources to discover and process documents.
            </p>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortField === 'filename'" :direction="$sortDirection" wire:click="sortBy('filename')">Filename</flux:table.column>
                <flux:table.column>Source</flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'status'" :direction="$sortDirection" wire:click="sortBy('status')">Status</flux:table.column>
                <flux:table.column sortable :sorted="$sortField === 'size_bytes'" :direction="$sortDirection" wire:click="sortBy('size_bytes')">Size</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
            @foreach ($documents as $document)
                <flux:table.row :key="$document->id">
                    <flux:table.cell>
                        <a href="{{ route('admin.documents.show', $document->id) }}" class="text-blue-600 hover:underline" wire:navigate>
                            {{ $document->filename }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge>{{ $document->knowledgeSource?->name ?? 'Unknown' }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="match($document->status) {
                            'indexed' => 'green',
                            'chunked', 'parsed' => 'blue',
                            'discovered' => 'yellow',
                            'error' => 'red',
                            'duplicate' => 'orange',
                            default => 'gray',
                        }">
                            {{ $document->status }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">{{ number_format($document->size_bytes / 1024, 1) }} KB</flux:table.cell>
                </flux:table.row>
            @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $documents->links() }}
        </div>
    @endif
</div>
