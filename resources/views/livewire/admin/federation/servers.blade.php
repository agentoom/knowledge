<div>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Federation Servers</flux:heading>
        <flux:button icon="plus" wire:click="$set('showCreateModal', true)" variant="primary" size="sm">
            Add Server
        </flux:button>
    </div>

    @if (session()->has('status'))
        <flux:callout color="green" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($servers as $server)
            <flux:card>
                <div class="flex items-start justify-between">
                    <div>
                        <flux:heading size="lg">{{ $server->name }}</flux:heading>
                        <p class="text-sm text-gray-500 mt-1 truncate">{{ $server->endpoint_url }}</p>
                    </div>
                    <flux:badge color="{{ $server->is_active ? 'green' : 'gray' }}">
                        {{ $server->is_active ? 'Active' : 'Inactive' }}
                    </flux:badge>
                </div>

                <p class="text-sm text-gray-600 mt-2">
                    <span class="font-medium">Priority:</span> {{ $server->priority }}
                </p>

                @if ($server->last_synced_at)
                    <p class="text-sm text-gray-500 mt-1">
                        Last synced: {{ $server->last_synced_at->diffForHumans() }}
                    </p>
                @endif

                @if ($server->remote_capabilities)
                    <div class="mt-3">
                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Remote Tools</p>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach ($server->remote_capabilities['tools'] ?? [] as $tool)
                                <flux:badge size="sm">{{ $tool }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-4 pt-3 border-t border-gray-100 flex gap-2">
                    <flux:button size="sm" icon="pencil" wire:click="edit({{ $server->id }})">Edit</flux:button>
                    <flux:button size="sm" icon="arrow-path" variant="outline" wire:click="sync({{ $server->id }})">Sync</flux:button>
                    <flux:button size="sm" icon="x-mark" variant="outline" color="red" wire:click="delete({{ $server->id }})">Remove</flux:button>
                </div>
            </flux:card>
        @endforeach
    </div>

    @if ($servers->isEmpty())
        <flux:card>
            <p class="text-gray-500">No federated servers configured. Add a remote Agentoom Knowledge server to query it alongside local sources.</p>
        </flux:card>
    @endif

    {{-- Create Modal --}}
    <flux:modal wire:model="showCreateModal" title="Add Federation Server">
        <div class="space-y-4">
            <flux:input wire:model="name" label="Server Name" placeholder="Production Knowledge Server" />
            <flux:input wire:model="endpointUrl" label="Endpoint URL" placeholder="https://knowledge.example.com/mcp" />
            <flux:input wire:model="authToken" label="API Key" type="password" placeholder="sk-..." />
            <flux:input wire:model="priority" label="Priority (higher = first)" type="number" min="0" />
            <flux:checkbox wire:model="isActive" label="Active" />
        </div>

        <x-slot:footer>
            <flux:button variant="outline" wire:click="$set('showCreateModal', false)">Cancel</flux:button>
            <flux:button variant="primary" wire:click="create">Add Server</flux:button>
        </x-slot>
    </flux:modal>

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" title="Edit Federation Server">
        <div class="space-y-4">
            <flux:input wire:model="editName" label="Server Name" />
            <flux:input wire:model="editEndpointUrl" label="Endpoint URL" />
            <flux:input wire:model="editAuthToken" label="API Key (leave blank to keep current)" type="password" placeholder="Leave blank to keep current key" />
            <flux:input wire:model="editPriority" label="Priority" type="number" min="0" />
            <flux:checkbox wire:model="editIsActive" label="Active" />
        </div>

        <x-slot:footer>
            <flux:button variant="outline" wire:click="$set('showEditModal', false)">Cancel</flux:button>
            <flux:button variant="primary" wire:click="update">Save Changes</flux:button>
        </x-slot>
    </flux:modal>
</div>
