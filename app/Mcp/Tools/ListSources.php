<?php

namespace App\Mcp\Tools;

use App\Knowledge\Services\MetadataRegistryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('List all available knowledge sources with their capabilities. Optionally filter by namespace.')]
class ListSources extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $registry = app(MetadataRegistryService::class)->get();
        $namespace = $request->get('namespace');

        if (empty($registry)) {
            return Response::text(json_encode([
                'sources' => [],
                'message' => 'No knowledge sources configured. Use the administration UI to add sources.',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $providers = $registry['providers'] ?? [];

        if ($namespace !== null) {
            $providers = array_values(array_filter($providers, function (array $provider) use ($namespace) {
                return $provider['namespace'] === $namespace;
            }));
        }

        return Response::text(json_encode([
            'sources' => $providers,
            'namespaces' => $registry['namespaces'] ?? [],
            'total_sources' => count($providers),
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
            'namespace' => $schema->createStringSchema(
                'Optional namespace to filter sources by (e.g., "docs", "erp").',
            ),
        ];
    }
}
