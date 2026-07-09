<div>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">MCP API Keys</flux:heading>
        <flux:button icon="plus" wire:click="$set('showCreateModal', true)">Generate Key</flux:button>
    </div>

    @if (session()->has('status'))
        <flux:callout color="green" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    @if ($newKeyPlain)
        <flux:callout color="yellow" class="mb-4">
            Copy this key now — it won't be shown again:<br>
            <code class="text-sm font-mono bg-zinc-100 dark:bg-zinc-800 dark:text-zinc-200 px-2 py-1 rounded border border-zinc-200 dark:border-zinc-700">{{ $newKeyPlain }}</code>
        </flux:callout>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Scopes</flux:table.column>
            <flux:table.column>Last Used</flux:table.column>
            <flux:table.column>Expires</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
        @foreach ($keys as $key)
            <flux:table.row :key="$key->id">
                <flux:table.cell>{{ $key->name }}</flux:table.cell>
                <flux:table.cell>
                    @foreach ($key->scopes as $scope)
                        <flux:badge size="sm">{{ $scope }}</flux:badge>
                    @endforeach
                </flux:table.cell>
                <flux:table.cell>{{ $key->last_used_at?->diffForHumans() ?? 'Never' }}</flux:table.cell>
                <flux:table.cell>
                    @if ($key->expires_at)
                        <flux:badge :color="$key->isExpired() ? 'red' : 'yellow'">
                            {{ $key->expires_at->toDateString() }}
                        </flux:badge>
                    @else
                        <span class="text-sm text-gray-500">Never</span>
                    @endif
                </flux:table.cell>
                <flux:table.cell>
                    <flux:button icon="trash" variant="subtle" size="sm" color="red" wire:click="revoke({{ $key->id }})" wire:confirm="Revoke this key?">
                        Revoke
                    </flux:button>
                </flux:table.cell>
            </flux:table.row>
        @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal wire:model="showCreateModal">
        <form wire:submit="create">
            <flux:heading size="lg">Generate API Key</flux:heading>

            <div class="mt-4 space-y-4">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="name" placeholder="MCP Client Key" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Scopes</flux:label>
                    <div class="space-y-2 mt-1">
                        @foreach (['knowledge:read', 'knowledge:write', 'admin:*', 'mcp:access', 'api:access'] as $scope)
                            <label class="flex items-center gap-2">
                                <flux:checkbox wire:model="scopes" value="{{ $scope }}" />
                                <span class="text-sm">{{ $scope }}</span>
                            </label>
                        @endforeach
                    </div>
                    <flux:error name="scopes" />
                </flux:field>

                <flux:field>
                    <flux:label>Expiration Date</flux:label>
                    <flux:input type="date" wire:model="expiresAt" />
                    <flux:description>Leave empty for a key that never expires.</flux:description>
                    <flux:error name="expiresAt" />
                </flux:field>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <flux:button variant="outline" wire:click="$set('showCreateModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">Generate</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
