<div>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Knowledge Sources</flux:heading>
        <a href="{{ route('admin.knowledge-sources.create') }}" wire:navigate>
            <flux:button icon="plus">
                Add Source
            </flux:button>
        </a>
    </div>

    @if (session()->has('status'))
        <flux:callout color="green" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    <div class="mb-4">
        <button
            type="button"
            wire:click="$set('showSourceTypesHelp', true)"
            class="inline-flex items-center gap-1.5 text-sm text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
        >
            <flux:icon name="question-mark-circle" class="size-4" />
            How source types affect retrieval
        </button>
    </div>

    <flux:modal wire:model="showSourceTypesHelp" class="max-w-3xl">
        <x-knowledge.source-types-info />
    </flux:modal>

    @if ($sources->isEmpty())
        <div class="flex flex-col items-center justify-center py-20 text-center">
            <div class="rounded-full bg-zinc-100 dark:bg-zinc-800 p-4 mb-4">
                <flux:icon name="book-open" class="size-10 text-zinc-300 dark:text-zinc-600" />
            </div>
            <h3 class="text-lg font-semibold text-zinc-600 dark:text-zinc-400 mb-1">No knowledge sources yet</h3>
            <p class="text-sm text-zinc-500 dark:text-zinc-500 max-w-md mb-6">
                Create your first knowledge source to start indexing documents, databases, or web content.
            </p>
            <a href="{{ route('admin.knowledge-sources.create') }}" wire:navigate>
                <flux:button icon="plus" variant="primary">Create Knowledge Source</flux:button>
            </a>
        </div>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Namespace</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
            @foreach ($sources as $source)
                <flux:table.row :key="$source->id">
                    <flux:table.cell>
                        <a href="{{ route('admin.knowledge-sources.show', $source->id) }}" class="text-blue-600 hover:underline" wire:navigate>
                            {{ $source->name }}
                        </a>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge>{{ $source->namespace }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <span class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $this->providerTypeLabel($source->provider_type) }}
                        </span>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$source->is_active ? 'green' : 'gray'">
                            {{ $source->is_active ? 'Active' : 'Inactive' }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <a href="{{ route('admin.knowledge-sources.show', ['source' => $source->id, 'edit' => 1]) }}" wire:navigate>
                            <flux:button icon="pencil" variant="subtle" size="sm">
                                Edit
                            </flux:button>
                        </a>
                        <flux:button icon="trash" variant="subtle" size="sm" color="red" wire:click="delete({{ $source->id }})" wire:confirm="Delete this source?">
                            Delete
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $sources->links() }}
        </div>
    @endif
</div>
