<?php

namespace App\Mcp\Resources;

use App\Mcp\Resources\Concerns\ResolvesKnowledgeSources;
use App\Mcp\Services\ResourceAuthorizationService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Source Documents')]
#[Title('Source Documents')]
#[Description('Documents of a single knowledge source, ordered newest first. Address the source by numeric id, slug, or namespace.')]
class ListDocumentsResource extends Resource implements HasUriTemplate
{
    use ResolvesKnowledgeSources;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('knowledge://sources/{source}/documents');
    }

    public function handle(Request $request): Response
    {
        $identifier = (string) ($request->get('source') ?? '');

        if ($identifier === '') {
            return Response::error('Missing the `source` identifier in the resource URI.');
        }

        $source = $this->resolveSource($identifier);

        if ($source === null) {
            return Response::error("Source '{$identifier}' not found. Use knowledge://sources to list available sources.");
        }

        if (! $source->is_active) {
            return Response::error("Source '{$identifier}' is not active.");
        }

        if (! app(ResourceAuthorizationService::class)->authorize($source->namespace)) {
            return Response::error("Not authorized to access source '{$identifier}'.");
        }

        $documents = $source->documents()
            ->orderByDesc('created_at')
            ->limit((int) config('knowledge.mcp_document_page_size', 50))
            ->get(['id', 'path', 'filename', 'mime_type', 'size_bytes', 'status', 'indexed_at']);

        return Response::text((string) json_encode([
            'source' => [
                'id' => $source->id,
                'name' => $source->name,
                'slug' => $source->slug,
                'namespace' => $source->namespace,
            ],
            'namespace' => $source->namespace,
            'total' => $documents->count(),
            'documents' => $documents->map(fn ($document): array => [
                'id' => $document->id,
                'path' => $document->path,
                'filename' => $document->filename,
                'mime_type' => $document->mime_type,
                'size_bytes' => $document->size_bytes,
                'status' => $document->status,
                'indexed_at' => $document->indexed_at?->toIso8601String(),
            ])->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
