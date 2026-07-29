<?php

namespace App\Jobs\DocumentPipeline;

use App\Contracts\ChunkingStrategy;
use App\DocumentPipeline\Services\ChunkingStrategyRegistry;
use App\Knowledge\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ChunkDocument implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $documentId,
    ) {}

    public function handle(): void
    {
        $document = Document::findOrFail($this->documentId);

        // Skip documents that are not in a state ready for chunking.
        if (! in_array($document->status, ['parsed', 'discovered'], true)) {
            return;
        }

        $content = $document->content ?? '';

        if ($content === '' || $content === '0') {
            $document->update([
                'status' => 'error',
                'error_message' => 'No content available for chunking.',
            ]);

            return;
        }

        $strategy = $this->resolveChunkingStrategy($document);

        $chunks = $strategy->chunk($content);

        $document->chunks()->delete();

        foreach ($chunks as $sequence => $chunkContent) {
            $chunk = $document->chunks()->create([
                'sequence' => $sequence,
                'content' => $chunkContent,
                'token_count' => str_word_count($chunkContent),
                'metadata' => [
                    'document_filename' => $document->filename,
                    'source_namespace' => $document->metadata['namespace'] ?? null,
                ],
            ]);

            EnrichChunk::dispatch($chunk->getKey());
        }

        $document->update([
            'status' => 'chunked',
            'chunked_at' => now(),
            'error_message' => null,
        ]);

        Log::info('Document chunked successfully.', [
            'document_id' => $document->getKey(),
            'chunk_count' => count($chunks),
        ]);
    }

    private function resolveChunkingStrategy(Document $document): ChunkingStrategy
    {
        $registry = app(ChunkingStrategyRegistry::class);

        return $registry->resolve(
            mimeType: $document->mime_type ?? '',
            filename: $document->filename,
        );
    }
}
