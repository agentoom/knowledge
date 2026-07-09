<div>
    <flux:heading size="xl" class="mb-6">Documents</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Filename</flux:table.column>
            <flux:table.column>Source</flux:table.column>
            <flux:table.column>Status</flux:table.column>
            <flux:table.column>Size</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
        @forelse ($documents as $document)
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
                        default => 'gray',
                    }">
                        {{ $document->status }}
                    </flux:badge>
                </flux:table.cell>
                <flux:table.cell>{{ number_format($document->size_bytes / 1024, 1) }} KB</flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="4" class="text-center text-gray-500">
                    No documents found. Run the document pipeline to discover documents.
                </flux:table.cell>
            </flux:table.row>
        @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $documents->links() }}
    </div>
</div>
