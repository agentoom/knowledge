<?php

namespace App\Providers\VectorStore;

use App\Contracts\KnowledgeProvider;
use App\Contracts\VectorStore;
use App\Embedding\Services\EmbeddingManager;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\RewrittenQuery;
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
        $expanded = $rewriter->expand($query->query);

        $driver = $this->vectorStore->driver();
        $embeddingManager = app(EmbeddingManager::class);

        // Managed mode only when the vector store advertises the capability AND
        // the active provider is the managed (typesense) mode.
        $managed = $embeddingManager->isManaged()
            && in_array('managed_embeddings', $driver->capabilities(), true);

        $hits = $this->execute($query, $expanded, $driver, $embeddingManager, $managed);

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
     * Run the driver search(es) for the expanded query.
     *
     * When synonym weighting is enabled and the query actually expanded, two
     * bounded searches run: pass A on the original query, pass B on the
     * rewritten query. Items are merged by chunk_id keeping pass-A order;
     * pass-B-only items are appended with their score penalized. Otherwise a
     * single search on the rewritten query runs — identical to the legacy path.
     *
     * @return array<int, array<string, mixed>>
     */
    private function execute(
        SearchQuery $query,
        RewrittenQuery $expanded,
        VectorStore $driver,
        EmbeddingManager $embeddingManager,
        bool $managed,
    ): array {
        if ($this->isWeightingEnabled() && $expanded->hasExpansion()) {
            $recall = min(250, max($query->maxResults * 2, 50));

            $passA = $this->rawSearch(
                queryText: $expanded->original,
                query: $query,
                limit: $recall,
                driver: $driver,
                embeddingManager: $embeddingManager,
                managed: $managed,
                usePassQueryAsKeyword: true,
            );

            $passB = $this->rawSearch(
                queryText: $expanded->rewritten,
                query: $query,
                limit: $recall,
                driver: $driver,
                embeddingManager: $embeddingManager,
                managed: $managed,
                usePassQueryAsKeyword: true,
            );

            return $this->mergeWeightedPasses($passA, $passB, $this->penaltyFactor(), $query->maxResults);
        }

        return $this->rawSearch(
            queryText: $expanded->rewritten,
            query: $query,
            limit: $query->maxResults,
            driver: $driver,
            embeddingManager: $embeddingManager,
            managed: $managed,
            usePassQueryAsKeyword: false,
        );
    }

    /**
     * Build the Typesense parameters for a supplied query text and return the
     * raw hits. The search type and namespace filter come from the query.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rawSearch(
        string $queryText,
        SearchQuery $query,
        int $limit,
        VectorStore $driver,
        EmbeddingManager $embeddingManager,
        bool $managed,
        bool $usePassQueryAsKeyword,
    ): array {
        $searchParams = [
            'q' => $queryText,
            'query_by' => 'content, document_filename',
        ];

        if ($query->searchType === 'hybrid') {
            $alpha = $this->getHybridAlpha();

            if ($managed) {
                // Typesense managed embeddings: the vector is computed from the
                // real pass query text. In the weighted two-pass mode the keyword
                // half keeps the pass query too, so original-term evidence is
                // not discarded; the legacy single-pass path keeps q=* (match-all).
                if (! $usePassQueryAsKeyword) {
                    $searchParams['q'] = '*';
                }

                $searchParams['vector_query'] = 'embedding:([], alpha: '.$alpha.', query: '.json_encode($queryText, JSON_THROW_ON_ERROR).')';
            } else {
                // External embedding provider: compute the query vector locally and
                // pass the raw vector — the driver translates it into vector_query
                // syntax while q keeps the keyword half of the hybrid search.
                $searchParams['vector_query'] = $embeddingManager
                    ->provider()
                    ->embed($queryText, 'search_query');
                $searchParams['vector_alpha'] = $alpha;
            }
        } elseif ($query->searchType === 'semantic' && ! $managed) {
            // Pure vector search with an external provider: match-all keyword
            // query plus a raw vector; the driver translates it into vector_query.
            $searchParams['q'] = '*';
            $searchParams['vector_query'] = $embeddingManager
                ->provider()
                ->embed($queryText, 'search_query');
        }

        if ($query->namespace && $query->namespace !== 'global') {
            $searchParams['filter_by'] = "namespace:={$query->namespace}";
        }

        return $driver->search(
            collection: $this->collection,
            query: $searchParams,
            limit: $limit
        );
    }

    /**
     * Merge the two passes by chunk_id, preserving pass-A order and applying
     * the penalty to pass-B-only items, then truncate to maxResults.
     *
     * @param  array<int, array<string, mixed>>  $passA
     * @param  array<int, array<string, mixed>>  $passB
     * @return array<int, array<string, mixed>>
     */
    private function mergeWeightedPasses(array $passA, array $passB, float $penalty, int $maxResults): array
    {
        $merged = [];
        $seen = [];

        foreach ($passA as $hit) {
            $chunkId = (string) ($hit['document']['chunk_id'] ?? '');

            if ($chunkId !== '') {
                $seen[$chunkId] = true;
            }

            $merged[] = $hit;
        }

        foreach ($passB as $hit) {
            $chunkId = (string) ($hit['document']['chunk_id'] ?? '');

            if ($chunkId !== '' && isset($seen[$chunkId])) {
                continue;
            }

            if ($chunkId !== '') {
                $seen[$chunkId] = true;
            }

            $hit['text_match'] = ($hit['text_match'] ?? 0) * $penalty;

            $merged[] = $hit;
        }

        return array_slice($merged, 0, $maxResults);
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

    private function isWeightingEnabled(): bool
    {
        return (bool) Settings::get('knowledge.synonym_weighting_enabled', false);
    }

    private function penaltyFactor(): float
    {
        $factor = (float) Settings::get('knowledge.synonym_penalty_factor', 0.5);

        return max(0.0, min(1.0, $factor));
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, $this->metadata()->supportedOperations);
    }
}
