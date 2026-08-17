<?php

namespace App\Mcp\Resources;

use App\Knowledge\Enums\ProviderStatus;
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

#[Name('Knowledge Source')]
#[Title('Knowledge Source')]
#[Description('Metadata for a single knowledge source, its active provider, and a bounded summary of its documents. Address by numeric id, slug, or namespace.')]
class SourceResource extends Resource implements HasUriTemplate
{
    use ResolvesKnowledgeSources;

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('knowledge://sources/{source}');
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

        $provider = $source->providers()
            ->where('status', ProviderStatus::Active->value)
            ->first();

        $documents = $source->documents()
            ->orderByDesc('created_at')
            ->limit((int) config('knowledge.mcp_source_document_summary', 20))
            ->get(['id', 'filename', 'mime_type', 'size_bytes', 'status', 'indexed_at', 'parsed_at']);

        return Response::text((string) json_encode([
            'source' => [
                'id' => $source->id,
                'name' => $source->name,
                'slug' => $source->slug,
                'description' => $source->description,
                'provider_type' => $source->provider_type,
                'namespace' => $source->namespace,
                'is_active' => $source->is_active,
                'priority' => $source->priority,
                'config_version' => $source->config_version,
                'created_at' => $source->created_at?->toIso8601String(),
                'updated_at' => $source->updated_at?->toIso8601String(),
            ],
            'provider' => $provider ? [
                'class' => $provider->class,
                'name' => $provider->name,
                'type' => $provider->type,
                'status' => $provider->status,
                'metadata' => $provider->metadata,
                'last_synced_at' => $provider->last_synced_at?->toIso8601String(),
            ] : null,
            'namespace' => $source->namespace,
            'document_summary' => [
                'count' => $source->documents()->count(),
                'documents' => $documents->map(fn ($document): array => [
                    'id' => $document->id,
                    'filename' => $document->filename,
                    'mime_type' => $document->mime_type,
                    'size_bytes' => $document->size_bytes,
                    'status' => $document->status,
                    'indexed_at' => $document->indexed_at?->toIso8601String(),
                    'parsed_at' => $document->parsed_at?->toIso8601String(),
                ])->all(),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
