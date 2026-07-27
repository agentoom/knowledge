<?php

namespace App\Livewire\Admin\Health;

use App\Knowledge\Models\Chunk;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\VectorStore\Services\VectorStoreManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    /**
     * @var array<string, array{status: string, message: string, icon: string}>
     */
    public array $checks = [];

    public int $totalSources = 0;

    public int $totalDocuments = 0;

    public int $totalChunks = 0;

    public string $queueDriver = '';

    public string $cacheDriver = '';

    public string $appEnv = '';

    public string $appDebug = '';

    public string $timestamp = '';

    public function mount(VectorStoreManager $vectorStoreManager): void
    {
        $this->checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'vector_store' => $this->checkVectorStore($vectorStoreManager),
            'typesense' => $this->checkTypesense($vectorStoreManager),
        ];

        $this->totalSources = KnowledgeSource::count();
        $this->totalDocuments = Document::count();
        $this->totalChunks = Chunk::count();
        $this->queueDriver = (string) config('queue.default');
        $this->cacheDriver = (string) config('cache.default');
        $this->appEnv = (string) app()->environment();
        $this->appDebug = var_export(config('app.debug'), true);
        $this->timestamp = now()->toDateTimeString();
    }

    /**
     * @return array{status: string, message: string, icon: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok', 'message' => 'Connected', 'icon' => 'folder'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage(), 'icon' => 'folder'];
        }
    }

    /**
     * @return array{status: string, message: string, icon: string}
     */
    private function checkCache(): array
    {
        try {
            Cache::store()->set('health_check', true, 1);

            return ['status' => 'ok', 'message' => 'Running ('.$this->cacheDriver.')', 'icon' => 'clock'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage(), 'icon' => 'clock'];
        }
    }

    /**
     * @return array{status: string, message: string, icon: string}
     */
    private function checkVectorStore(VectorStoreManager $manager): array
    {
        try {
            $healthy = $manager->driver()->healthCheck();

            return [
                'status' => $healthy ? 'ok' : 'warning',
                'message' => $healthy ? 'Healthy' : 'Not responding',
                'icon' => 'server',
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage(), 'icon' => 'server'];
        }
    }

    /**
     * @return array{status: string, message: string, icon: string}
     */
    private function checkTypesense(VectorStoreManager $manager): array
    {
        // Reuse the vector store check if Typesense is the active driver
        // or perform a dedicated Typesense check if available
        try {
            $driverName = (string) config('vector-store.default');
            if ($driverName === 'typesense' || $driverName === '') {
                return $this->checkVectorStore($manager);
            }

            return ['status' => 'warning', 'message' => 'Driver: '.$driverName.' (not Typesense)', 'icon' => 'magnifying-glass'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage(), 'icon' => 'magnifying-glass'];
        }
    }

    public function getOverallStatus(): string
    {
        $statuses = array_column($this->checks, 'status');

        if (in_array('error', $statuses)) {
            return 'error';
        }

        if (in_array('warning', $statuses)) {
            return 'warning';
        }

        return 'ok';
    }

    public function getOkCount(): int
    {
        return count(array_filter($this->checks, fn ($c) => $c['status'] === 'ok'));
    }

    public function refresh(): void
    {
        $this->mount(app(VectorStoreManager::class));
    }

    public function render(): View
    {
        return view('livewire.admin.health.dashboard')
            ->layout('layouts.app', ['header' => 'Health']);
    }
}
