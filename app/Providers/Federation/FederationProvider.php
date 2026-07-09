<?php

namespace App\Providers\Federation;

use App\Contracts\KnowledgeProvider;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Acts as an MCP client to a remote Agentoom Knowledge server.
 *
 * Translates local SearchQuery objects into MCP JSON-RPC tool calls
 * on the remote server's search_knowledge endpoint.
 */
class FederationProvider implements KnowledgeProvider
{
    public function __construct(
        private readonly string $endpointUrl,
        private readonly string $authToken,
        private readonly string $serverName,
    ) {}

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            capabilities: ['search', 'list_resources', 'federated'],
            searchableResources: ["federated_{$this->serverName}"],
            searchableFields: ['content', 'filename', 'namespace'],
            namespace: "federation.{$this->serverName}",
            supportedOperations: ['full_text', 'semantic', 'structured_filter'],
        );
    }

    public function search(SearchQuery $query): SearchResult
    {
        try {
            $response = Http::timeout(30)
                ->withToken($this->authToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpointUrl, [
                    'jsonrpc' => '2.0',
                    'method' => 'tools/call',
                    'params' => [
                        'name' => 'search_knowledge',
                        'arguments' => [
                            'query' => $query->query,
                            'namespace' => $query->namespace,
                            'max_results' => $query->maxResults,
                            'filters' => $query->filters,
                            'search_type' => $query->searchType,
                        ],
                    ],
                    'id' => 1,
                ]);

            if (! $response->successful()) {
                Log::warning('FederationProvider: remote search failed.', [
                    'server' => $this->serverName,
                    'status' => $response->status(),
                ]);

                return new SearchResult(
                    items: [],
                    totalCount: 0,
                    providerName: "federation.{$this->serverName}",
                );
            }

            $body = $response->json();

            $resultContent = $body['result']['content'][0]['text'] ?? '{}';
            $data = json_decode($resultContent, true);

            if (! is_array($data)) {
                return new SearchResult(
                    items: [],
                    totalCount: 0,
                    providerName: "federation.{$this->serverName}",
                );
            }

            $items = $data['items'] ?? [];

            // Tag each item with its federation source
            $items = array_map(function (array $item) {
                $item['_federation_source'] = $this->serverName;

                return $item;
            }, $items);

            return new SearchResult(
                items: $items,
                totalCount: count($items),
                providerName: "federation.{$this->serverName}",
                metadata: [
                    'server' => $this->serverName,
                    'endpoint' => $this->endpointUrl,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('FederationProvider: search exception.', [
                'server' => $this->serverName,
                'error' => $e->getMessage(),
            ]);

            return new SearchResult(
                items: [],
                totalCount: 0,
                providerName: "federation.{$this->serverName}",
            );
        }
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, $this->metadata()->supportedOperations, true);
    }
}
