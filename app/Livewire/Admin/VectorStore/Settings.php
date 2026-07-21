<?php

namespace App\Livewire\Admin\VectorStore;

use App\VectorStore\Services\VectorStoreManager;
use Illuminate\View\View;
use Livewire\Component;

class Settings extends Component
{
    public string $driver = 'typesense';

    public bool $isHealthy = false;

    /**
     * @var array<int, string>
     */
    public array $capabilities = [];

    public function mount(VectorStoreManager $manager): void
    {
        $this->driver = $manager->getDefaultDriver();
        $this->isHealthy = $manager->driver()->healthCheck();
        $this->capabilities = $manager->capabilities();
    }

    public function render(): View
    {
        return view('livewire.admin.vector-store.settings');
    }
}
