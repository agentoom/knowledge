<?php

namespace App\Jobs\DocumentPipeline;

use App\Contracts\VectorStore as VectorStoreContract;
use App\Embedding\Services\EmbeddingManager;
use App\Knowledge\Models\Chunk;
use App\VectorStore\Services\VectorStoreManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class IndexChunk implements ShouldQueue
{
    use Queueable;

    private const COLLECTION = 'knowledge_chunks';

    /**
     * Collection dimensions already verified in this process. Avoids a
     * schema round-trip per chunk once the collection is known-compatible.
     *
     * @var array<string, int>
     */
    private static array $verifiedCollectionDimensions = [];

    public function __construct(
        public readonly int $chunkId,
    ) {}

    public function handle(VectorStoreManager $vectorStore): void
    {
        $chunk = Chunk::findOrFail($this->chunkId);

        try {
            $document = $chunk->document;

            $driver = $vectorStore->driver();

            $embeddingManager = app(EmbeddingManager::class);

            // Managed embeddings only when the vector store advertises the
            // capability AND the active provider is the managed (typesense)
            // mode. Selecting an external provider switches to client-side
            // vectors even though Typesense remains the vector store.
            $managed = $embeddingManager->isManaged()
                && in_array('managed_embeddings', $driver->capabilities(), true);

            $embedding = null;

            if ($managed) {
                // Managed embeddings: Typesense computes the vector from the
                // content field via its built-in embed model. No client vector.
                $driver->ensureCollectionExists(self::COLLECTION, [
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
            } else {
                // External embedding provider: fixed-dimension float[] vector
                // field, computed client-side and passed through index().
                $provider = $embeddingManager->provider();
                $dimensions = $provider->dimensions();

                $this->assertCollectionCompatible($driver, $dimensions);

                $driver->ensureCollectionExists(self::COLLECTION, [
                    'fields' => [
                        ['name' => 'chunk_id', 'type' => 'int64'],
                        ['name' => 'content', 'type' => 'string'],
                        ['name' => 'sequence', 'type' => 'int32'],
                        ['name' => 'document_id', 'type' => 'int64'],
                        ['name' => 'document_filename', 'type' => 'string'],
                        ['name' => 'namespace', 'type' => 'string', 'facet' => true, 'optional' => true],
                        ['name' => 'embedding_hash', 'type' => 'string'],
                        ['name' => 'embedding', 'type' => 'float[]', 'num_dim' => $dimensions],
                    ],
                    'default_sorting_field' => 'chunk_id',
                ]);

                $embedding = $provider->embed($chunk->content, 'search_document');
            }

            $driver->index(
                collection: self::COLLECTION,
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
                embedding: $embedding,
            );

            $chunk->update([
                'indexed_at' => now(),
                'vector_store_id' => $document->knowledgeSource?->id,
            ]);

            // When all chunks of the document are indexed, mark the document.
            $pendingChunks = $document->chunks()->whereNull('indexed_at')->count();

            if ($pendingChunks === 0) {
                $document->update([
                    'status' => 'indexed',
                    'indexed_at' => now(),
                ]);
            }

            Log::debug('Chunk indexed successfully.', [
                'chunk_id' => $chunk->id,
                'collection' => self::COLLECTION,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to index chunk.', [
                'chunk_id' => $chunk->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fail fast when an existing collection would reject the provider's
     * vector dimension, instead of silently indexing into an incompatible
     * collection. Verified dimensions are cached per process.
     */
    private function assertCollectionCompatible(VectorStoreContract $driver, int $dimensions): void
    {
        if ((self::$verifiedCollectionDimensions[self::COLLECTION] ?? null) === $dimensions) {
            return;
        }

        if (method_exists($driver, 'collectionSchema')) {
            $schema = $driver->collectionSchema(self::COLLECTION);

            if ($schema !== null) {
                $fields = $schema['fields'] ?? [];

                foreach ($fields as $field) {
                    if (($field['name'] ?? null) !== 'embedding') {
                        continue;
                    }

                    $existing = (int) ($field['num_dim'] ?? 0);

                    if ($existing !== $dimensions) {
                        throw new RuntimeException(
                            'The knowledge_chunks collection exists with an embedding field incompatible with the active provider '
                            ."(expected {$dimensions} dimensions, found ".($existing > 0 ? $existing : 'unmanaged/unknown').'). '
                            .'Reindex the collection or reset the search index before switching embedding providers.'
                        );
                    }

                    break;
                }
            }
        }

        self::$verifiedCollectionDimensions[self::COLLECTION] = $dimensions;
    }
}
