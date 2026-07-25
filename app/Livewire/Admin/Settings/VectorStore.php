<?php

namespace App\Livewire\Admin\Settings;

use App\VectorStore\Models\VectorStore as VectorStoreModel;
use Illuminate\View\View;
use Livewire\Component;

class VectorStore extends Component
{
    public string $host = '';

    public string $port = '8108';

    public string $protocol = 'http';

    public string $apiKey = '';

    public bool $isActive = true;

    public function mount(): void
    {
        $store = VectorStoreModel::where('driver', 'typesense')->first();

        if ($store) {
            $config = $store->config ?? [];

            $this->host = $this->extractHost($config['host'] ?? 'http://typesense:8108');
            $this->port = (string) ($config['port'] ?? '8108');
            $this->protocol = $config['protocol'] ?? 'http';
            $this->apiKey = $config['api_key'] ?? '';
            $this->isActive = (bool) $store->is_active;
        }
    }

    public function save(): void
    {
        $this->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'string', 'max:5'],
            'protocol' => ['required', 'in:http,https'],
            'apiKey' => ['required', 'string', 'max:255'],
        ]);

        $store = VectorStoreModel::where('driver', 'typesense')->first();

        if (! $store) {
            $store = new VectorStoreModel(['driver' => 'typesense']);
        }

        $store->config = [
            'host' => $this->buildHost(),
            'port' => $this->port,
            'protocol' => $this->protocol,
            'api_key' => $this->apiKey,
        ];
        $store->is_active = $this->isActive;
        $store->save();

        $this->dispatch('notify', message: 'Vector store settings saved successfully.');
        $this->dispatch('settings-clean');
    }

    public function render(): View
    {
        return view('livewire.admin.settings.vector-store');
    }

    private function extractHost(string $host): string
    {
        $host = preg_replace('#^https?://#', '', $host);
        $host = preg_replace('#:\d+$#', '', $host);

        return $host;
    }

    private function buildHost(): string
    {
        return "{$this->protocol}://{$this->host}:{$this->port}";
    }
}
