<?php

namespace App\Livewire\Admin\Dashboard;

use App\Knowledge\Models\Chunk;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Knowledge\Services\ProviderManager;
use App\Models\RetrievalLog;
use App\VectorStore\Services\VectorStoreManager;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Livewire\Component;

class Index extends Component
{
    public int $totalSources = 0;

    public int $activeProviders = 0;

    public int $totalDocuments = 0;

    public int $totalChunks = 0;

    public bool $vectorStoreHealthy = false;

    public string $queueStatus = 'inactive';

    public array $recentLogs = [];

    public array $vectorStoreStats = [];

    public string $timestamp = '';

    public function mount(
        ProviderManager $providerManager,
        VectorStoreManager $vectorStoreManager,
        MasterSupervisorRepository $horizon,
    ): void {
        $this->totalSources = KnowledgeSource::count();
        $this->activeProviders = $providerManager->getCount();
        $this->totalDocuments = Document::count();
        $this->totalChunks = Chunk::count();

        $this->vectorStoreHealthy = $vectorStoreManager->driver()->healthCheck();
        $this->vectorStoreStats = $this->vectorStoreHealthy ? $vectorStoreManager->driver()->stats() : [];

        $masters = $horizon->all();
        $this->queueStatus = collect($masters)->contains(fn ($m) => $m->status === 'running') ? 'active' : 'inactive';

        $this->recentLogs = RetrievalLog::latest()->take(5)->get()->toArray();

        $this->timestamp = now()->toDateTimeString();
    }

    public function render()
    {
        return view('livewire.admin.dashboard.index')
            ->layout('layouts.app', ['header' => 'Dashboard']);
    }
}
