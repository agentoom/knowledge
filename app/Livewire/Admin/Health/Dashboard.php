<?php

namespace App\Livewire\Admin\Health;

use App\VectorStore\Services\VectorStoreManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
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

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            Cache::store()->set('health_check', true, 1);

            return ['status' => 'ok', 'message' => 'Running'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function checkVectorStore(VectorStoreManager $manager): array
    {
        try {
            $healthy = $manager->driver()->healthCheck();

            return ['status' => $healthy ? 'ok' : 'warning', 'message' => $healthy ? 'Healthy' : 'Not responding'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function render()
    {
        return view('livewire.admin.health.dashboard')
            ->layout('layouts.app', ['header' => 'Health']);
    }
}
