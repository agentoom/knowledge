<?php

namespace App\Livewire\Admin\Dashboard;

use App\Knowledge\Models\Chunk;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Knowledge\Services\ProviderManager;
use App\Models\RetrievalLog;
use App\VectorStore\Services\VectorStoreManager;
use Illuminate\View\View;
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

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $recentLogs = [];

    /**
     * @var array<string, mixed>
     */
    public array $vectorStoreStats = [];

    public string $timestamp = '';

    public function mount(
        ProviderManager $providerManager,
        VectorStoreManager $vectorStoreManager,
    ): void {
        $this->totalSources = KnowledgeSource::count();
        $this->activeProviders = $providerManager->getCount();
        $this->totalDocuments = Document::count();
        $this->totalChunks = Chunk::count();

        try {
            $this->vectorStoreHealthy = $vectorStoreManager->driver()->healthCheck();
            $this->vectorStoreStats = $this->vectorStoreHealthy ? $vectorStoreManager->driver()->stats() : [];
        } catch (\Throwable) {
            $this->vectorStoreHealthy = false;
            $this->vectorStoreStats = [];
        }

        $this->queueStatus = $this->resolveQueueStatus();

        $this->recentLogs = RetrievalLog::latest()->take(5)->get()->toArray();

        $this->timestamp = now()->toDateTimeString();
    }

    private function resolveQueueStatus(): string
    {
        try {
            $horizon = app(MasterSupervisorRepository::class);
            $masters = $horizon->all();

            /** @var array<int, object{status: string}> $masters */
            return collect($masters)->contains(fn ($m) => $m->status === 'running') ? 'active' : 'inactive';
        } catch (\Throwable) {
            return 'inactive';
        }
    }

    public function render(): View
    {
        return view('livewire.admin.dashboard.index')
            ->layout('layouts.app', ['header' => 'Dashboard']);
    }
}
