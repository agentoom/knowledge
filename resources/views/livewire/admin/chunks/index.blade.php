<div>
    <flux:heading size="xl" class="mb-6">Chunks</flux:heading>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Document</flux:table.column>
            <flux:table.column>Sequence</flux:table.column>
            <flux:table.column>Tokens</flux:table.column>
            <flux:table.column>Indexed</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
        @forelse ($chunks as $chunk)
            <flux:table.row :key="$chunk->id">
                <flux:table.cell>{{ $chunk->document?->filename ?? 'Unknown' }}</flux:table.cell>
                <flux:table.cell>#{{ $chunk->sequence }}</flux:table.cell>
                <flux:table.cell>{{ $chunk->token_count }}</flux:table.cell>
                <flux:table.cell>
                    <flux:badge :color="$chunk->indexed_at ? 'green' : 'yellow'">
                        {{ $chunk->indexed_at ? 'Indexed' : 'Pending' }}
                    </flux:badge>
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="4" class="text-center text-gray-500">
                    No chunks found. Run the document pipeline to process documents.
                </flux:table.cell>
            </flux:table.row>
        @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $chunks->links() }}
    </div>
</div>
