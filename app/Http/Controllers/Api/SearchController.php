<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Planning\Services\QueryPlanner;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Services\RetrievalEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request, QueryPlanner $planner, RetrievalEngine $engine): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:1',
            'namespace' => 'nullable|string',
            'max_results' => 'nullable|integer|min:1|max:100',
            'filters' => 'nullable|array',
            'search_type' => 'nullable|string|in:semantic,structured,hybrid',
        ]);

        $searchQuery = new SearchQuery(
            query: $validated['query'],
            namespace: $validated['namespace'] ?? null,
            maxResults: $validated['max_results'] ?? 10,
            filters: $validated['filters'] ?? [],
            searchType: $validated['search_type'] ?? null,
        );

        $plan = $planner->plan($searchQuery);
        $result = $engine->execute($plan);

        return response()->json([
            'items' => $result->items,
            'total_count' => $result->totalCount,
            'strategy' => $plan->strategy,
        ]);
    }
}
