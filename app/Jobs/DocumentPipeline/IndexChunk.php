<?php

namespace App\Jobs\DocumentPipeline;

use App\Knowledge\Models\Chunk;
use App\VectorStore\Services\VectorStoreManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class IndexChunk implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $chunkId,
    ) {}

    public function handle(VectorStoreManager $vectorStore): void
    {
        $chunk = Chunk::findOrFail($this->chunkId);

        try {
            $collection = 'knowledge_chunks';

            $document = $chunk->document;

            $driver = $vectorStore->driver();

            $driver->ensureCollectionExists($collection, [
                'fields' => [
                    ['name' => 'chunk_id', 'type' => 'int64'],
                    ['name' => 'content', 'type' => 'string'],
                    ['name' => 'sequence', 'type' => 'int32'],
                    ['name' => 'document_id', 'type' => 'int64'],
                    ['name' => 'document_filename', 'type' => 'string'],
                    ['name' => 'namespace', 'type' => 'string', 'facet' => true, 'optional' => true],
                    ['name' => 'embedding_hash', 'type' => 'string'],
                    ['name' => 'embedding', 'type' => 'float[]', 'embed' => [
                        'from' => ['content'],
                        'model_config' => ['model_name' => 'ts/all-MiniLM-L12-v2'],
                    ]],
                ],
                'default_sorting_field' => 'chunk_id',
            ]);

            $driver->index(
                collection: $collection,
                id: (string) $chunk->id,
                document: [
                    'chunk_id' => $chunk->id,
                    'content' => $chunk->content,
                    'sequence' => $chunk->sequence,
                    'document_id' => $document->id,
                    'document_filename' => $document->filename,
                    'namespace' => $chunk->metadata['source_namespace'] ?? null,
                    'embedding_hash' => $chunk->embedding_hash,
                ],
            );

            $chunk->update([
                'indexed_at' => now(),
                'vector_store_id' => $document->knowledgeSource?->id,
            ]);

            Log::debug('Chunk indexed successfully.', [
                'chunk_id' => $chunk->id,
                'collection' => $collection,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to index chunk.', [
                'chunk_id' => $chunk->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
