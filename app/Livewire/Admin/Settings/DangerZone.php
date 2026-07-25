<?php

namespace App\Livewire\Admin\Settings;

use App\Knowledge\Models\KnowledgeSource;
use App\VectorStore\Services\VectorStoreManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;

class DangerZone extends Component
{
    public bool $showConfirmModal = false;

    public bool $resetting = false;

    public ?string $resultMessage = null;

    public ?string $resultError = null;

    public function confirmReset(): void
    {
        $this->showConfirmModal = true;
    }

    public function cancelReset(): void
    {
        $this->showConfirmModal = false;
    }

    public function resetApp(): void
    {
        $this->showConfirmModal = false;
        $this->resetting = true;
        $this->resultMessage = null;
        $this->resultError = null;

        try {
            $stats = $this->performReset();

            $this->resultMessage = sprintf(
                'App reset complete. Removed %d knowledge sources, %d retrieval logs, and cleared all indexed data.',
                $stats['sources'],
                $stats['logs']
            );

            Log::warning('App reset performed via Danger Zone.', $stats);
        } catch (\Throwable $e) {
            $this->resultError = 'Reset failed: '.$e->getMessage();

            Log::error('App reset failed.', ['error' => $e->getMessage()]);
        } finally {
            $this->resetting = false;
        }
    }

    /**
     * @return array{sources: int, logs: int, chunks_indexed: int}
     */
    private function performReset(): array
    {
        // 1. Drop the Typesense collection to remove all indexed data.
        try {
            $vectorStore = app(VectorStoreManager::class)->driver();
            $vectorStore->deleteCollection('knowledge_chunks');
        } catch (\Throwable $e) {
            Log::warning('Could not drop Typesense collection during reset.', [
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Delete all knowledge sources (cascade-deletes documents, providers, chunks).
        $sourceCount = KnowledgeSource::count();
        KnowledgeSource::query()->delete();

        // 3. Truncate retrieval logs.
        $logCount = DB::table('retrieval_logs')->count();
        DB::table('retrieval_logs')->truncate();

        // 4. Clear metadata registry.
        DB::table('metadata_registry')->truncate();

        // 5. Clear job-related tables.
        DB::table('job_tracking')->truncate();
        DB::table('job_batches')->truncate();
        DB::table('jobs')->delete();
        DB::table('failed_jobs')->truncate();

        // 6. Clear activity log.
        DB::table('activity_log')->truncate();

        // 7. Clear application cache.
        Cache::flush();

        return [
            'sources' => $sourceCount,
            'logs' => $logCount,
            'chunks_indexed' => 0,
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.settings.danger-zone');
    }
}
