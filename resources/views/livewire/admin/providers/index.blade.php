<div>
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Providers</flux:heading>
        @if (!empty($providers))
            <flux:button icon="arrow-path" wire:click="syncAll" variant="outline" size="sm">
                Sync All
            </flux:button>
        @endif
    </div>

    @if (session()->has('status'))
        <flux:callout color="green" class="mb-4">{{ session('status') }}</flux:callout>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($providers as $provider)
            <flux:card>
                <div class="flex items-start justify-between">
                    <div>
                        <flux:heading size="lg">{{ $provider['name'] }}</flux:heading>
                        <p class="text-sm text-gray-500 mt-1">{{ $provider['class'] }}</p>
                    </div>
                    <flux:badge color="{{ $provider['status'] === 'active' ? 'green' : ($provider['status'] === 'error' ? 'red' : ($provider['status'] === 'syncing' ? 'yellow' : 'gray')) }}">
                        {{ $provider['status'] }}
                    </flux:badge>
                </div>

                @if ($provider['source_name'])
                    <p class="text-sm text-gray-600 mt-2">
                        <span class="font-medium">Source:</span> {{ $provider['source_name'] }}
                    </p>
                @endif

                @if ($provider['namespace'])
                    <p class="text-sm text-gray-600 mt-1">
                        <span class="font-medium">Namespace:</span> {{ $provider['namespace'] }}
                    </p>
                @endif

                @if ($provider['last_synced_at'])
                    <p class="text-sm text-gray-500 mt-1">Last synced: {{ $provider['last_synced_at'] }}</p>
                @endif

                @if ($provider['error_message'])
                    <p class="text-sm text-red-500 mt-1">{{ $provider['error_message'] }}</p>
                @endif

                <div class="mt-4">
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Capabilities</p>
                    <div class="flex flex-wrap gap-1 mt-1">
                        @foreach ($provider['capabilities'] as $cap)
                            <flux:badge size="sm">{{ $cap }}</flux:badge>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-100 flex gap-2">
                    <flux:button size="sm" icon="wrench-screwdriver" :href="route('admin.providers.configure', $provider['id'])">Configure</flux:button>
                    <flux:button size="sm" icon="arrow-path" variant="outline" wire:click="sync({{ $provider['id'] }})">Sync</flux:button>
                </div>
            </flux:card>
        @endforeach
    </div>

    @if (empty($providers))
        <flux:card>
            <p class="text-gray-500">No providers registered. Add knowledge sources to register providers.</p>
        </flux:card>
    @endif
</div>
