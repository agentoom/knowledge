<?php

namespace App\Livewire\Admin\Providers;

use App\Events\ProviderSynced;
use App\Knowledge\Models\Provider;
use App\Knowledge\Services\ProviderManager;
use Carbon\CarbonInterface;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $providers = [];

    public function mount(ProviderManager $providerManager): void
    {
        $this->loadProviders();
    }

    public function loadProviders(): void
    {
        $this->providers = Provider::query()
            ->with('knowledgeSource')
            ->get()
            ->map(fn (Provider $provider) => [
                'id' => $provider->id,
                'class' => $provider->class,
                'name' => $provider->name,
                'type' => $provider->type,
                'status' => $provider->status,
                'last_synced_at' => $provider->last_synced_at instanceof CarbonInterface
                    ? $provider->last_synced_at->diffForHumans()
                    : null,
                'error_message' => $provider->error_message,
                'capabilities' => $provider->metadata['capabilities'] ?? [],
                'namespace' => $provider->metadata['namespace'] ?? null,
                'source_name' => $provider->knowledgeSource?->name,
            ])
            ->values()
            ->all();
    }

    public function sync(int $providerId): void
    {
        $provider = Provider::findOrFail($providerId);

        $provider->update([
            'last_synced_at' => now(),
            'status' => 'active',
            'error_message' => null,
        ]);

        ProviderSynced::dispatch($provider);

        $this->loadProviders();

        session()->flash('status', "Provider '{$provider->name}' synced successfully.");
    }

    public function syncAll(): void
    {
        $providers = Provider::all();

        foreach ($providers as $provider) {
            $provider->update([
                'last_synced_at' => now(),
                'status' => 'active',
                'error_message' => null,
            ]);

            ProviderSynced::dispatch($provider);
        }

        $this->loadProviders();

        session()->flash('status', 'All providers synced successfully.');
    }

    public function render(): View
    {
        return view('livewire.admin.providers.index')
            ->layout('layouts.app', ['header' => 'Providers']);
    }
}
