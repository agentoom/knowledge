<?php

namespace App\Jobs\DocumentPipeline;

use App\DocumentPipeline\Services\TokenCounter;
use App\Knowledge\Models\Chunk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class EnrichChunk implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $chunkId,
    ) {}

    public function handle(): void
    {
        $chunk = Chunk::findOrFail($this->chunkId);

        $document = $chunk->document;

        $documentMetadata = is_array($document->metadata) ? $document->metadata : [];
        $chunkMetadata = is_array($chunk->metadata) ? $chunk->metadata : [];

        $chunkMetadata['source_namespace'] = $chunkMetadata['source_namespace']
            ?? $documentMetadata['namespace']
            ?? null;

        $chunkMetadata['document_filename'] = $chunkMetadata['document_filename']
            ?? $document->filename;

        $chunkMetadata['chunk_size_bytes'] = strlen($chunk->content ?? '');

        // Must agree with the token_count persisted by ChunkDocument: the
        // document detail view and the indexed metadata share this counter.
        $chunkMetadata['token_count'] = app(TokenCounter::class)->count($chunk->content ?? '');

        $embeddingPayload = json_encode([
            'content' => $chunk->content,
            'namespace' => $chunkMetadata['source_namespace'],
        ]);

        $chunkMetadata['embedding_hash'] = md5($embeddingPayload ?: '');

        $chunk->update([
            'metadata' => $chunkMetadata,
            'token_count' => $chunkMetadata['token_count'],
            'embedding_hash' => $chunkMetadata['embedding_hash'],
        ]);

        IndexChunk::dispatch($chunk->id);

        Log::debug('Chunk enriched.', [
            'chunk_id' => $chunk->id,
            'token_count' => $chunkMetadata['token_count'],
        ]);
    }
}
