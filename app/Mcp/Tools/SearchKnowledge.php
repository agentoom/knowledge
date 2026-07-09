<?php

namespace App\Mcp\Tools;

use App\Planning\Services\QueryPlanner;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Services\RetrievalEngine;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Unified search across all knowledge sources. The server determines the best retrieval strategy internally. Use `filters` for structured filtering and `search_type` to constrain the retrieval approach.')]
class SearchKnowledge extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $query = new SearchQuery(
            query: (string) $request->get('query', ''),
            namespace: $request->get('namespace'),
            maxResults: (int) $request->get('max_results', 10),
            filters: (array) $request->get('filters', []),
            searchType: $request->get('search_type'),
        );

        if ($query->query === '') {
            return Response::error('The `query` parameter is required.');
        }

        $planner = app(QueryPlanner::class);
        $plan = $planner->plan($query);

        $engine = app(RetrievalEngine::class);
        $result = $engine->execute($plan);

        return Response::text(json_encode([
            'items' => $result->items,
            'total_count' => $result->totalCount,
            'providers_queried' => collect($plan->steps)->pluck('providerClass')->unique()->values()->all(),
            'strategy' => $plan->strategy,
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
            'query' => $schema->createStringSchema(
                'The search query string.',
            ),
            'namespace' => $schema->createStringSchema(
                'Optional namespace to scope the search (e.g., "docs", "erp", "hr").',
            ),
            'max_results' => $schema->createIntegerSchema(
                'Maximum number of results to return. Defaults to 10.',
            ),
            'filters' => $schema->createObjectSchema(
                'Optional structured filters for narrowing results. Keys and values depend on the provider.',
                additionalProperties: $schema->createStringSchema('Filter value.'),
            ),
            'search_type' => $schema->createStringSchema(
                'Optional search type: "semantic", "structured", or "hybrid". Defaults to "hybrid" which lets the planner decide.',
            ),
        ];
    }
}
