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

            $document->update([
                'content' => $result['content'],
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
