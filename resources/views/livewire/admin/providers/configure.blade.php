<div>
    <div class="flex items-center gap-4 mb-6">
        <flux:button icon="arrow-left" size="sm" :href="route('admin.providers.index')" wire:navigate>
            Back to Providers
        </flux:button>
        <flux:heading size="xl">{{ $name }}</flux:heading>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Configuration Form --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">Provider Configuration</flux:heading>

            <form wire:submit="save" class="space-y-4">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input wire:model="name" />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>Type</flux:label>
                    <flux:input wire:model="type" />
                    <flux:error name="type" />
                </flux:field>

                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:select wire:model="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="error">Error</option>
                        <option value="syncing">Syncing</option>
                    </flux:select>
                    <flux:error name="status" />
                </flux:field>

                <flux:field>
                    <flux:label>Class</flux:label>
                    <flux:input :value="$class" disabled />
                    <flux:description>The PHP class that implements this provider. Cannot be changed here.</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>Metadata (JSON)</flux:label>
                    <textarea
                        wire:model="metadata"
                        rows="8"
                        class="w-full border border-zinc-200 dark:border-zinc-700 rounded-lg p-3 text-sm font-mono bg-white dark:bg-zinc-800"
                        placeholder='{"namespace": "docs", "capabilities": ["search"]}'
                    ></textarea>
                    <flux:error name="metadata" />
                </flux:field>

                <div class="pt-2">
                    <flux:button type="submit" variant="primary" icon="check">
                        Save Configuration
                    </flux:button>
                </div>
            </form>
        </flux:card>

        {{-- Provider Info --}}
        <flux:card>
            <flux:heading size="lg" class="mb-4">Provider Details</flux:heading>
            <div class="space-y-3">
                <div>
                    <span class="text-sm font-medium text-gray-600">Knowledge Source</span>
                    <p>{{ $sourceName ?? 'N/A' }}</p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-600">Current Status</span>
                    <p>
                        <flux:badge color="{{ $status === 'active' ? 'green' : ($status === 'error' ? 'red' : ($status === 'syncing' ? 'yellow' : 'gray')) }}">
                            {{ $status }}
                        </flux:badge>
                    </p>
                </div>
                <div>
                    <span class="text-sm font-medium text-gray-600">Last Synced</span>
                    <p>{{ $lastSyncedAt ?? 'Never' }}</p>
                </div>
                @if ($errorMessage)
                <div>
                    <span class="text-sm font-medium text-gray-600">Error</span>
                    <p class="text-red-600">{{ $errorMessage }}</p>
                </div>
                @endif
                <div>
                    <span class="text-sm font-medium text-gray-600">Database ID</span>
                    <p class="text-sm text-gray-500">{{ $providerId }}</p>
                </div>
            </div>
        </flux:card>
    </div>
</div>
