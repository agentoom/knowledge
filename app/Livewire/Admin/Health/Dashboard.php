<?php

namespace App\Livewire\Admin\Health;

use App\VectorStore\Services\VectorStoreManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    /**
     * @var array<string, array<string, mixed>>
     */
    public array $checks = [];

    public function mount(VectorStoreManager $vectorStoreManager): void
    {
        $this->checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'vector_store' => $this->checkVectorStore($vectorStoreManager),
            'typesense' => $this->checkVectorStore($vectorStoreManager),
        ];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkCache(): array
    {
        try {
            Cache::store()->set('health_check', true, 1);

            return ['status' => 'ok', 'message' => 'Running'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function checkVectorStore(VectorStoreManager $manager): array
    {
        try {
            $healthy = $manager->driver()->healthCheck();

            return ['status' => $healthy ? 'ok' : 'warning', 'message' => $healthy ? 'Healthy' : 'Not responding'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function render(): View
    {
        return view('livewire.admin.health.dashboard')
            ->layout('layouts.app', ['header' => 'Health']);
    }
}
