<?php

namespace App\Mcp\Resources;

use App\Knowledge\Models\Document;
use App\Mcp\Services\ResourceAuthorizationService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('Document')]
#[Title('Document')]
#[Description('Metadata and full parsed content for a single document. Address by numeric document id.')]
class DocumentResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('knowledge://documents/{id}');
    }

    public function handle(Request $request): Response
    {
        $identifier = (string) ($request->get('id') ?? '');

        if ($identifier === '' || ! ctype_digit($identifier)) {
            return Response::error('The `id` URI variable must be a numeric document id.');
        }

        $document = Document::with('knowledgeSource')->find((int) $identifier);

        if ($document === null) {
            return Response::error("Document '{$identifier}' not found.");
        }

        $source = $document->knowledgeSource;

        if ($source === null || ! $source->is_active) {
            return Response::error("Document '{$identifier}' belongs to an inactive source.");
        }

        if (! app(ResourceAuthorizationService::class)->authorize($source->namespace)) {
            return Response::error("Not authorized to access document '{$identifier}'.");
        }

        $content = trim((string) $document->content);

        if (in_array($document->status, ['error', 'duplicate'], true) || $content === '') {
            return Response::error("Document '{$identifier}' is not available (status: {$document->status}).");
        }

        return Response::text((string) json_encode([
            'document' => [
                'id' => $document->id,
                'filename' => $document->filename,
                'path' => $document->path,
                'mime_type' => $document->mime_type,
                'size_bytes' => $document->size_bytes,
                'status' => $document->status,
                'content_hash' => $document->content_hash,
                'metadata' => $document->metadata,
                'parsed_at' => $document->parsed_at?->toIso8601String(),
                'chunked_at' => $document->chunked_at?->toIso8601String(),
                'indexed_at' => $document->indexed_at?->toIso8601String(),
            ],
            'namespace' => $source->namespace,
            'content' => $content,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
