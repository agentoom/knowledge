<?php

namespace App\Livewire\Admin\Jobs;

use Illuminate\Support\Facades\Redis;
use Livewire\Component;

class Index extends Component
{
    public array $queueStats = [];

    public int $recentJobs = 0;

    public int $failedJobs = 0;

    public bool $horizonRunning = false;

    public function mount(): void
    {
        $this->refreshStats();
    }

    public function refreshStats(): void
    {
        $horizonPrefix = config('horizon.prefix', 'agentoom_knowledge_horizon:');

        // Horizon stores data on the default Redis connection (config: horizon.use = 'default')
        $redis = Redis::connection();

        // Check if Horizon is running by looking for its master supervisor key
        $masterKey = $redis->keys($horizonPrefix.'master:*');
        $this->horizonRunning = ! empty($masterKey);

        if ($this->horizonRunning) {
            // Get recent jobs count
            $recentKeys = $redis->keys($horizonPrefix.'recent_jobs:*');
            $this->recentJobs = count($recentKeys);

            // Get failed jobs count
            $failedKeys = $redis->keys($horizonPrefix.'failed_jobs:*');
            $this->failedJobs = count($failedKeys);

            // Get queue workload stats
            $this->queueStats = $this->getQueueWorkload($horizonPrefix);
        }
    }

    protected function getQueueWorkload(string $prefix): array
    {
        $redis = Redis::connection();
        $queues = ['default', 'documents', 'providers', 'search', 'notifications'];
        $stats = [];

        foreach ($queues as $queue) {
            $pending = $redis->llen($prefix.'queue:'.$queue.':pending') ?: 0;

            $processed = $redis->get($prefix.'queue:'.$queue.':processed') ?: 0;

            $stats[$queue] = [
                'pending' => (int) $pending,
                'processed' => (int) $processed,
            ];
        }

        return $stats;
    }

    public function render()
    {
        return view('livewire.admin.jobs.index', [
            'horizonUrl' => url(config('horizon.path', 'horizon')),
        ]);
    }
}
