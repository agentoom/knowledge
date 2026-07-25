<?php

namespace App\Console\Commands\Knowledge;

use App\Jobs\DocumentPipeline\IndexChunk;
use App\Knowledge\Models\Chunk;
use Illuminate\Console\Command;

class IndexExistingChunks extends Command
{
    protected $signature = 'knowledge:chunks:index';

    protected $description = 'Dispatch IndexChunk jobs for all unindexed chunks';

    public function handle(): int
    {
        $chunks = Chunk::whereNull('indexed_at')->get();

        if ($chunks->isEmpty()) {
            $this->info('All chunks are already indexed.');

            return self::SUCCESS;
        }

        $this->info("Dispatching IndexChunk jobs for {$chunks->count()} chunks...");

        foreach ($chunks as $chunk) {
            IndexChunk::dispatch($chunk->id);
        }

        $this->info('Jobs dispatched. Run queue worker to process them.');

        return self::SUCCESS;
    }
}
