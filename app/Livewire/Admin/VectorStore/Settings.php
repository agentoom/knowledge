<?php

namespace App\Livewire\Admin\VectorStore;

use App\VectorStore\Services\VectorStoreManager;
use Livewire\Component;

class Settings extends Component
{
    public string $driver = 'typesense';

    public bool $isHealthy = false;

    public array $capabilities = [];

    public function mount(VectorStoreManager $manager): void
    {
        $this->driver = $manager->getDefaultDriver();
        $this->isHealthy = $manager->driver()->healthCheck();
        $this->capabilities = $manager->capabilities();
    }

    public function render()
    {
        return view('livewire.admin.vector-store.settings');
    }
}
