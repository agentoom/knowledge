<?php

namespace App\Jobs\DocumentPipeline;

use App\DocumentPipeline\Parsers\TikaParser;
use App\Knowledge\Models\Document;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ParseDocument implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public function __construct(
        public readonly int $documentId,
    ) {}

    public function handle(TikaParser $parser): void
    {
        $document = Document::findOrFail($this->documentId);

        try {
            $result = $parser->parse($document->path);

            $content = $result['content'];
            $contentHash = hash('sha256', $content);

            // Check if this parsed content already exists in another non-stale, non-duplicate document.
            $duplicate = Document::where('content_hash', $contentHash)
                ->where('id', '!=', $document->id)
                ->whereNotIn('status', ['stale', 'duplicate', 'error'])
                ->exists();

            if ($duplicate) {
                // De-index any chunks that may have been indexed (from a prior run).
                $chunkIds = $document->chunks()->pluck('id')->toArray();

                if (! empty($chunkIds)) {
                    DeindexDocument::dispatch($chunkIds);
                }

                $document->update([
                    'content' => $content,
                    'content_hash' => $contentHash,
                    'status' => 'duplicate',
                    'parsed_at' => now(),
                    'error_message' => 'Duplicate content detected: another document has the same SHA-256 content hash.',
                ]);

                Log::info('Document marked as duplicate — identical content already exists.', [
                    'document_id' => $document->id,
                    'filename' => $document->filename,
                    'content_hash' => $contentHash,
                ]);

                return;
            }

            $document->update([
                'content' => $content,
                'content_hash' => $contentHash,
                'mime_type' => $result['metadata']['Content-Type'] ?? $document->mime_type,
                'status' => 'parsed',
                'parsed_at' => now(),
                'error_message' => null,
            ]);

            Log::info('Document parsed successfully.', [
                'document_id' => $document->id,
                'filename' => $document->filename,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to parse document.', [
                'document_id' => $document->id,
                'filename' => $document->filename,
                'error' => $e->getMessage(),
            ]);

            $document->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
