<?php

namespace App\Providers\VectorStore;

use App\Contracts\KnowledgeProvider;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use App\VectorStore\Services\VectorStoreManager;

class SemanticProvider implements KnowledgeProvider
{
    public function __construct(
        private readonly VectorStoreManager $vectorStore,
        private readonly string $collection = 'knowledge_chunks'
    ) {}

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            capabilities: ['search', 'semantic_search'],
            searchableResources: [$this->collection],
            searchableFields: ['content', 'document_filename'],
            namespace: 'semantic',
            supportedOperations: ['semantic', 'hybrid'],
        );
    }

    public function search(SearchQuery $query): SearchResult
    {
        $searchParams = [
            'q' => $query->query,
            'query_by' => 'content, document_filename',
        ];

        if ($query->namespace && $query->namespace !== 'global') {
            $searchParams['filter_by'] = "namespace:={$query->namespace}";
        }

        $hits = $this->vectorStore->driver()->search(
            collection: $this->collection,
            query: $searchParams,
            limit: $query->maxResults
        );

        $items = array_map(function (array $hit) {
            $doc = $hit['document'];

            return [
                'content' => $doc['content'] ?? '',
                'filename' => $doc['document_filename'] ?? 'unknown',
                'document_id' => $doc['document_id'] ?? null,
                'chunk_id' => $doc['chunk_id'] ?? null,
                'score' => $hit['text_match'] ?? 0,
                'namespace' => $doc['namespace'] ?? null,
            ];
        }, $hits);

        return new SearchResult(
            items: $items,
            totalCount: count($items),
            providerName: 'semantic',
            metadata: ['collection' => $this->collection]
        );
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, $this->metadata()->supportedOperations);
    }
}
