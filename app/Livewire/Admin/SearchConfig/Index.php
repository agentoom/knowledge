<?php

namespace App\Livewire\Admin\SearchConfig;

use App\Settings\Facades\Settings;
use App\VectorStore\Services\VectorStoreManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;

class Index extends Component
{
    public string $defaultPlannerStrategy = 'federation';

    public string $defaultFusionStrategy = 'reciprocal_rank_fusion';

    public int $defaultMaxResults = 10;

    public string $defaultChunkingStrategy = 'fixed_size';

    public int $chunkSize = 1000;

    public int $chunkOverlap = 200;

    /**
     * @var array<string, string>
     */
    public array $availablePlannerStrategies = [];

    /**
     * @var array<string, string>
     */
    public array $availableFusionStrategies = [];

    /**
     * @var array<string, string>
     */
    public array $availableChunkingStrategies = [];

    // Vector store read-only info
    public string $vectorDriver = '';

    public bool $vectorHealthy = false;

    /**
     * @var array<string, mixed>
     */
    public array $vectorStats = [];

    /**
     * @var array<int, string>
     */
    public array $vectorCapabilities = [];

    public function mount(VectorStoreManager $vectorStoreManager): void
    {
        $settings = Settings::getMany([
            'knowledge.default_planner_strategy',
            'knowledge.default_fusion_strategy',
            'knowledge.default_max_results',
            'knowledge.default_chunking_strategy',
            'knowledge.chunk_size',
            'knowledge.chunk_overlap',
        ], [
            'knowledge.default_planner_strategy' => 'federation',
            'knowledge.default_fusion_strategy' => 'reciprocal_rank_fusion',
            'knowledge.default_max_results' => 10,
            'knowledge.default_chunking_strategy' => 'fixed_size',
            'knowledge.chunk_size' => 1000,
            'knowledge.chunk_overlap' => 200,
        ]);

        $this->defaultPlannerStrategy = $settings['knowledge.default_planner_strategy'];
        $this->defaultFusionStrategy = $settings['knowledge.default_fusion_strategy'];
        $this->defaultMaxResults = (int) $settings['knowledge.default_max_results'];
        $this->defaultChunkingStrategy = $settings['knowledge.default_chunking_strategy'];
        $this->chunkSize = (int) $settings['knowledge.chunk_size'];
        $this->chunkOverlap = (int) $settings['knowledge.chunk_overlap'];

        $this->availablePlannerStrategies = [
            'default' => 'Default (Rule-based)',
            'federation' => 'Federation (Local + Remote)',
        ];

        $this->availableFusionStrategies = [
            'reciprocal_rank_fusion' => 'Reciprocal Rank Fusion (RRF)',
        ];

        $this->availableChunkingStrategies = [
            'fixed_size' => 'Fixed Size',
            'markdown' => 'Markdown',
            'recursive' => 'Recursive',
        ];

        // Vector store read-only info (cached: 30s staleness is acceptable)
        $this->vectorDriver = $vectorStoreManager->getDefaultDriver();

        try {
            $this->vectorHealthy = Cache::remember('vector_store_health', 30, function () use ($vectorStoreManager): bool {
                return $vectorStoreManager->driver()->healthCheck();
            });

            $this->vectorStats = Cache::remember('vector_store_stats', 30, function () use ($vectorStoreManager): array {
                return $vectorStoreManager->driver()->stats();
            });
        } catch (\Throwable $e) {
            Log::warning('SearchConfig: vector store health check failed.', [
                'error' => $e->getMessage(),
            ]);

            $this->vectorHealthy = false;
            $this->vectorStats = ['document_count' => 0];
        }

        $this->vectorCapabilities = $vectorStoreManager->capabilities();
    }

    public function save(): void
    {
        $this->validate([
            'defaultMaxResults' => ['required', 'integer', 'min:1', 'max:100'],
            'chunkSize' => ['required', 'integer', 'min:100', 'max:10000'],
            'chunkOverlap' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        Settings::set('knowledge.default_planner_strategy', $this->defaultPlannerStrategy, 'string');
        Settings::set('knowledge.default_fusion_strategy', $this->defaultFusionStrategy, 'string');
        Settings::set('knowledge.default_max_results', $this->defaultMaxResults, 'integer');
        Settings::set('knowledge.default_chunking_strategy', $this->defaultChunkingStrategy, 'string');
        Settings::set('knowledge.chunk_size', $this->chunkSize, 'integer');
        Settings::set('knowledge.chunk_overlap', $this->chunkOverlap, 'integer');

        $this->dispatch('notify', message: 'Search configuration saved successfully.');
        $this->dispatch('settings-clean');
    }

    public function render(): View
    {
        return view('livewire.admin.search-config.index')
            ->layout('layouts.app', ['header' => 'Search Configuration']);
    }
}
