<?php

namespace App\Contracts;

use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;

/**
 * Marks a KnowledgeProvider as remotely callable via HTTP.
 *
 * Implementations expose the details needed to construct an HTTP request
 * so that RetrievalEngine can batch them via Http::pool() for concurrent,
 * non-blocking execution.
 */
interface RemoteProvider extends KnowledgeProvider
{
    /**
     * The remote endpoint URL to POST search requests to.
     */
    public function getEndpointUrl(): string;

    /**
     * The Bearer auth token for the remote endpoint.
     */
    public function getAuthToken(): string;

    /**
     * Human-readable server name for logging and tracing.
     */
    public function getServerName(): string;

    /**
     * Build the JSON-RPC payload body from a SearchQuery.
     *
     * @return array<string, mixed>
     */
    public function buildSearchPayload(SearchQuery $query): array;

    /**
     * Parse the raw JSON-RPC response body into a SearchResult.
     *
     * @param  array<string, mixed>  $body
     */
    public function parseSearchResponse(array $body, string $serverName): SearchResult;
}
