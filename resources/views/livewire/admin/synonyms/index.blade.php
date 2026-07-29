<div>
    <flux:heading size="lg" class="mb-4">Synonym Groups</flux:heading>

    <flux:card class="mb-6">
        <div class="space-y-4">
            <flux:description>
                Define groups of equivalent words to expand search queries automatically.
                When synonym expansion is enabled, searching for any word in a group will
                also match all other words in that group.
            </flux:description>

            {{-- Add new group --}}
            <form wire:submit="create" class="flex gap-3 items-start">
                <flux:field class="flex-1">
                    <flux:input
                        wire:model="newWords"
                        placeholder="e.g. car, automobile, vehicle"
                    />
                    <flux:error name="newWords" />
                    <flux:description>Comma-separated list of synonymous words (minimum 2).</flux:description>
                </flux:field>
                <flux:button type="submit" variant="primary" class="mt-0.5">Add</flux:button>
            </form>
        </div>
    </flux:card>

    {{-- Existing groups --}}
    @if ($groups->isNotEmpty())
        <flux:card>
            <div class="space-y-3">
                @foreach ($groups as $group)
                    <div class="flex items-start justify-between gap-4 py-2 {{ !$loop->last ? 'border-b border-zinc-200 dark:border-zinc-700' : '' }}">
                        @if ($editingId === $group->id)
                            <div class="flex-1 flex gap-3 items-start">
                                <flux:field class="flex-1">
                                    <flux:input
                                        wire:model="editingWords"
                                        placeholder="e.g. car, automobile, vehicle"
                                    />
                                    <flux:error name="editingWords" />
                                </flux:field>
                                <flux:button
                                    type="button"
                                    variant="primary"
                                    size="sm"
                                    wire:click="update"
                                    class="mt-0.5"
                                >
                                    Save
                                </flux:button>
                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    wire:click="cancelEdit"
                                    class="mt-0.5"
                                >
                                    Cancel
                                </flux:button>
                            </div>
                        @else
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($group->words as $word)
                                    <flux:badge variant="subtle" color="blue">{{ $word }}</flux:badge>
                                @endforeach
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    icon="pencil"
                                    wire:click="startEdit({{ $group->id }})"
                                />
                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    wire:click="delete({{ $group->id }})"
                                    wire:confirm="Delete this synonym group?"
                                />
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </flux:card>
    @else
        <flux:card>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 text-center py-4">
                No synonym groups configured yet. Add one above to enable query expansion.
            </p>
        </flux:card>
    @endif

    <flux:separator class="my-6" />

    <div class="text-sm text-zinc-500 dark:text-zinc-400">
        <p>Synonym expansion can be toggled on or off in the <a href="{{ route('admin.settings') }}" class="underline text-blue-600 dark:text-blue-400">Settings → Search Configuration</a> page.</p>
    </div>
</div>
