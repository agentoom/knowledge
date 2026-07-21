<?php

namespace App\Livewire\Admin\Documents;

use App\Knowledge\Models\Document;
use Carbon\CarbonInterface;
use Illuminate\View\View;
use Livewire\Component;

class Show extends Component
{
    public ?string $filename = null;

    public ?string $status = null;

    public ?int $sizeBytes = null;

    public ?string $mimeType = null;

    public ?string $path = null;

    public ?string $content = null;

    public ?string $errorMessage = null;

    public ?string $parsedAt = null;

    public ?string $chunkedAt = null;

    public ?string $indexedAt = null;

    public ?string $createdAt = null;

    public ?string $sourceName = null;

    /**
     * @var array<int, array{id: int, content_preview: string, sequence: int, token_count: int, created_at: string|null}>
     */
    public array $chunks = [];

    public function mount(int $document): void
    {
        $document = Document::with(['knowledgeSource', 'chunks'])->findOrFail($document);

        $this->filename = $document->filename;
        $this->status = $document->status;
        $this->sizeBytes = $document->size_bytes;
        $this->mimeType = $document->mime_type;
        $this->path = $document->path;
        $this->content = $document->content;
        $this->errorMessage = $document->error_message;
        /** @var CarbonInterface $parsedAt */
        $parsedAt = $document->parsed_at;
        /** @var CarbonInterface $chunkedAt */
        $chunkedAt = $document->chunked_at;
        /** @var CarbonInterface $indexedAt */
        $indexedAt = $document->indexed_at;
        /** @var CarbonInterface $createdAt */
        $createdAt = $document->created_at;
        $this->parsedAt = $parsedAt->toDateTimeString();
        $this->chunkedAt = $chunkedAt->toDateTimeString();
        $this->indexedAt = $indexedAt->toDateTimeString();
        $this->createdAt = $createdAt->toDateTimeString();
        $this->sourceName = $document->knowledgeSource?->name;
        $this->chunks = $document->chunks->map(fn ($chunk) => [
            'id' => $chunk->id,
            'content_preview' => mb_strimwidth($chunk->content ?? '', 0, 200, '...'),
            'sequence' => $chunk->sequence,
            'token_count' => $chunk->token_count,
            'created_at' => $chunk->created_at?->toDateTimeString(),
        ])->all();
    }

    public function render(): View
    {
        return view('livewire.admin.documents.show')
            ->layout('layouts.app', ['header' => 'Document Detail']);
    }
}
