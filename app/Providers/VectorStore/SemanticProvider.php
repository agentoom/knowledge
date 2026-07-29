<?php

namespace App\Providers\VectorStore;

use App\Contracts\KnowledgeProvider;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use App\Retrieval\Services\QueryRewriter;
use App\Settings\Facades\Settings;
use App\VectorStore\Services\VectorStoreManager;
use Illuminate\Support\Facades\DB;

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
        $rewriter = app(QueryRewriter::class);
        $rewrittenQuery = $rewriter->rewrite($query->query);

        $searchParams = [
            'q' => $rewrittenQuery,
            'query_by' => 'content, document_filename',
        ];

        if ($query->searchType === 'hybrid') {
            $alpha = $this->getHybridAlpha();
            // Typesense managed embeddings: use q=* (match-all) and pass the actual
            // query text inside vector_query so Typesense computes the vector from it.
            $searchParams['q'] = '*';
            $searchParams['vector_query'] = 'embedding:([], alpha: '.$alpha.', query: '.json_encode($rewrittenQuery, JSON_THROW_ON_ERROR).')';
        }

        if ($query->namespace && $query->namespace !== 'global') {
            $searchParams['filter_by'] = "namespace:={$query->namespace}";
        }

        $hits = $this->vectorStore->driver()->search(
            collection: $this->collection,
            query: $searchParams,
            limit: $query->maxResults
        );

        // Filter out hits that reference non-indexed documents (duplicate, stale, error).
        $validDocIds = DB::table('documents')
            ->where('status', 'indexed')
            ->pluck('id')
            ->toArray();

        $items = array_values(array_filter(array_map(function (array $hit) use ($validDocIds) {
            $doc = $hit['document'];
            $documentId = $doc['document_id'] ?? null;

            // Filter out non-indexed documents and empty content
            if ($documentId !== null && ! in_array((int) $documentId, $validDocIds, true)) {
                return null;
            }

            $content = $doc['content'] ?? '';

            // Skip chunks with empty or HTML-only content
            $stripped = trim(strip_tags($content));
            if ($stripped === '' || $stripped === '0') {
                return null;
            }

            $item = [
                'id' => (string) ($doc['chunk_id'] ?? ''),
                'content' => $content,
                'filename' => $doc['document_filename'] ?? 'unknown',
                'document_id' => $doc['document_id'] ?? null,
                'chunk_id' => $doc['chunk_id'] ?? null,
                'score' => $hit['text_match'] ?? 0,
                'namespace' => $doc['namespace'] ?? null,
            ];

            // Include indexed_at for recency-aware fusion
            if (isset($hit['document']['created_at'])) {
                $item['indexed_at'] = $hit['document']['created_at'];
            }

            return $item;
        }, $hits)));

        return new SearchResult(
            items: $items,
            totalCount: count($items),
            providerName: 'semantic',
            metadata: ['collection' => $this->collection]
        );
    }

    /**
     * Read the hybrid search alpha parameter from settings.
     *
     * Alpha controls the keyword vs. vector balance in Typesense hybrid search.
     * 0.0 = pure vector, 1.0 = pure keyword, 0.5 = equal weight (default).
     */
    private function getHybridAlpha(): float
    {
        $alpha = Settings::get('knowledge.hybrid_alpha', 0.5);

        return (float) max(0.0, min(1.0, $alpha));
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, $this->metadata()->supportedOperations);
    }
}
