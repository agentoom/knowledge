<?php

namespace App\Mcp\Tools;

use App\Knowledge\Services\MetadataRegistryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Get the schema and capabilities for a specific knowledge source identified by its namespace or class name.')]
class GetSourceSchema extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $sourceId = $request->get('source_id');

        if ($sourceId === null || $sourceId === '') {
            return Response::error('The `source_id` parameter is required. Use `list_sources` to see available sources.');
        }

        $registry = app(MetadataRegistryService::class)->get();

        if (empty($registry)) {
            return Response::error('No knowledge sources configured.');
        }

        $providers = $registry['providers'] ?? [];
        $schemas = $registry['schemas'] ?? [];

        $provider = null;
        foreach ($providers as $p) {
            if ($p['class'] === $sourceId || $p['namespace'] === $sourceId) {
                $provider = $p;
                break;
            }
        }

        if ($provider === null) {
            return Response::error("Source '{$sourceId}' not found. Use `list_sources` to see available sources.");
        }

        $namespace = $provider['namespace'];

        return Response::text((string) json_encode([
            'source' => $provider,
            'schema' => $schemas[$namespace] ?? null,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'source_id' => $schema->string()
                ->description('The source identifier: either the provider class name or namespace (e.g., "App\\Providers\\Filesystem\\FilesystemProvider" or "docs").'),
        ];
    }
}
