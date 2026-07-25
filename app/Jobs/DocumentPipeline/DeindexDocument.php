<?php

namespace App\Jobs\DocumentPipeline;

use App\VectorStore\Services\VectorStoreManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Remove indexed chunks from the vector store when a document is deleted.
 *
 * Dispatched from the Document model's deleting event with the chunk IDs
 * captured before DB cascade-deletion removes them.
 */
class DeindexDocument implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, int>  $chunkIds
     */
    public function __construct(
        public readonly array $chunkIds,
        public readonly string $collection = 'knowledge_chunks',
    ) {}

    public function handle(VectorStoreManager $vectorStore): void
    {
        $driver = $vectorStore->driver();
        $deleted = 0;
        $failed = 0;

        foreach ($this->chunkIds as $chunkId) {
            try {
                $driver->delete($this->collection, (string) $chunkId);
                $deleted++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('DeindexDocument: failed to delete chunk from index.', [
                    'chunk_id' => $chunkId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('DeindexDocument: completed.', [
            'total' => count($this->chunkIds),
            'deleted' => $deleted,
            'failed' => $failed,
        ]);
    }
}
