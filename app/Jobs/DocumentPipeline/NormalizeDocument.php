<?php

namespace App\Jobs\DocumentPipeline;

use App\Knowledge\Models\Document;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NormalizeDocument implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public function __construct(public readonly int $documentId) {}

    public function handle(): void
    {
        $document = Document::findOrFail($this->documentId);

        $content = $document->content ?? '';

        if ($content === '' || $content === '0') {
            ChunkDocument::dispatch($document->id);

            return;
        }

        $content = $this->normalize($content);

        $document->update(['content' => $content]);

        ChunkDocument::dispatch($document->id);
    }

    private function normalize(string $content): string
    {
        $content = preg_replace('/\r\n|\r/', "\n", $content);
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        $content = preg_replace('/[ \t]+/', ' ', $content);

        return trim($content);
    }
}
