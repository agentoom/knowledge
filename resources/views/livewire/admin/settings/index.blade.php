<div
    x-data="{ dirty: false }"
    @settings-dirty.window="dirty = true"
    @settings-clean.window="dirty = false"
>
    <flux:heading size="xl" class="mb-6">Settings</flux:heading>

    {{-- Tabs --}}
    <div class="mb-6 border-b border-zinc-200 dark:border-zinc-700">
        <nav class="-mb-px flex gap-6" aria-label="Settings tabs">
            @foreach ([
                'general'       => 'General',
                'search-config' => 'Search Config',
                'vector-store'  => 'Vector Store',
                'embedding'     => 'Embedding',
                'storage'       => 'Storage',
                'notifications' => 'Notifications',
                'rate-limiting' => 'Rate Limiting',
                'maintenance'   => 'Maintenance',
                'danger-zone'   => 'Danger Zone',
            ] as $value => $label)
                <button
                    type="button"
                    @click="
                        if ('{{ $tab }}' !== '{{ $value }}' && dirty && !confirm('You have unsaved changes. Switch tabs anyway?')) {
                            return;
                        }
                        dirty = false;
                        $wire.selectTab('{{ $value }}');
                    "
                    @class([
                        'pb-3 px-1 text-sm font-medium border-b-2 transition-colors cursor-pointer',
                        'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' => $tab === $value,
                        'border-red-600 text-red-600 dark:border-red-400 dark:text-red-400' => $tab === $value && $value === 'danger-zone',
                        'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300 dark:hover:border-zinc-600' => $tab !== $value && $value !== 'danger-zone',
                        'border-transparent text-red-500 hover:text-red-700 hover:border-red-300 dark:text-red-400 dark:hover:text-red-300 dark:hover:border-red-600' => $tab !== $value && $value === 'danger-zone',
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- Tab Content — all tabs rendered, inactive ones hidden via CSS to avoid remount flicker --}}
    <div wire:key="tab-general" @class(['hidden' => $tab !== 'general'])>
        @livewire(\App\Livewire\Admin\Settings\General::class)
    </div>
    <div wire:key="tab-search-config" @class(['hidden' => $tab !== 'search-config'])>
        @livewire(\App\Livewire\Admin\SearchConfig\Index::class)
    </div>
    <div wire:key="tab-vector-store" @class(['hidden' => $tab !== 'vector-store'])>
        @livewire(\App\Livewire\Admin\Settings\VectorStore::class)
    </div>
    <div wire:key="tab-embedding" @class(['hidden' => $tab !== 'embedding'])>
        @livewire(\App\Livewire\Admin\Settings\Embedding::class)
    </div>
    <div wire:key="tab-storage" @class(['hidden' => $tab !== 'storage'])>
        @livewire(\App\Livewire\Admin\Settings\Storage::class)
    </div>
    <div wire:key="tab-notifications" @class(['hidden' => $tab !== 'notifications'])>
        @livewire(\App\Livewire\Admin\Settings\Notifications::class)
    </div>
    <div wire:key="tab-rate-limiting" @class(['hidden' => $tab !== 'rate-limiting'])>
        @livewire(\App\Livewire\Admin\Settings\RateLimiting::class)
    </div>
    <div wire:key="tab-maintenance" @class(['hidden' => $tab !== 'maintenance'])>
        @livewire(\App\Livewire\Admin\Settings\Maintenance::class)
    </div>
    <div wire:key="tab-danger-zone" @class(['hidden' => $tab !== 'danger-zone'])>
        @livewire(\App\Livewire\Admin\Settings\DangerZone::class)
    </div>
</div>
