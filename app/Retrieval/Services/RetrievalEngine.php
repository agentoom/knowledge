<?php

namespace App\Retrieval\Services;

use App\Contracts\ResultFusionStrategy;
use App\Events\RetrievalExecuted;
use App\Federation\FederationManager;
use App\Knowledge\Services\ProviderManager;
use App\Models\RetrievalLog;
use App\Retrieval\Models\ExecutionPlan;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Log;

class RetrievalEngine
{
    public function __construct(
        private readonly ResultFusionStrategy $fusion,
        private readonly ProviderManager $providerManager,
    ) {}

    public function execute(ExecutionPlan $plan): SearchResult
    {
        $start = microtime(true);

        $results = Concurrency::run($this->buildCallbacks($plan));

        $fused = $this->fusion->fuse($results);

        $latency = (int) ((microtime(true) - $start) * 1000);

        $this->logRetrieval($plan, $fused, $latency);

        RetrievalExecuted::dispatch(
            query: $plan->query?->query ?? '',
            resultCount: count($fused),
            durationMs: $latency,
            providersQueried: count($plan->steps),
        );

        return new SearchResult(
            items: $fused,
            totalCount: count($fused),
            providerName: 'fused',
        );
    }

    /**
     * @param  array<int, mixed>  $fusedResults
     */
    private function logRetrieval(ExecutionPlan $plan, array $fusedResults, int $latency): void
    {
        try {
            RetrievalLog::create([
                'tenant_id' => $plan->query?->filters['tenant_id'] ?? null, // Example extraction
                'query' => $plan->query?->query ?? 'unknown',
                'execution_plan' => $plan->steps,
                'fused_results' => $fusedResults,
                'metadata' => [
                    'strategy' => $plan->strategy,
                    'namespace' => $plan->query?->namespace,
                    'filters' => $plan->query?->filters,
                ],
                'latency_ms' => $latency,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log retrieval.', [
                'error' => $e->getMessage(),
                'query' => $plan->query?->query,
            ]);
        }
    }

    /**
     * @return array<int, callable(): SearchResult>
     */
    private function buildCallbacks(ExecutionPlan $plan): array
    {
        return collect($plan->steps)->map(function ($step) {
            // Federation step: resolve via FederationManager
            if ($step->providerClass === '__federation__') {
                $federationProvider = $step->parameters['_federation_provider'] ?? null;

                if ($federationProvider !== null) {
                    return static function () use ($federationProvider, $step): SearchResult {
                        $searchQuery = new SearchQuery(
                            query: $step->parameters['query'] ?? '',
                            namespace: $step->parameters['namespace'] ?? null,
                            maxResults: $step->parameters['maxResults'] ?? 10,
                            filters: $step->parameters['filters'] ?? [],
                            searchType: $step->operation === 'search' ? null : $step->operation,
                        );

                        return $federationProvider->search($searchQuery);
                    };
                }

                return static function (): SearchResult {
                    return new SearchResult(
                        items: [],
                        totalCount: 0,
                        providerName: 'federation.error',
                    );
                };
            }

            // Standard local provider step
            return static function () use ($step): SearchResult {
                $providerManager = app(ProviderManager::class);
                $providerModel = $providerManager->getByClass($step->providerClass);

                if ($providerModel === null) {
                    Log::warning('Provider not found for plan step.', [
                        'providerClass' => $step->providerClass,
                    ]);

                    return new SearchResult(
                        items: [],
                        totalCount: 0,
                        providerName: $step->providerClass,
                    );
                }

                $provider = $providerModel->toKnowledgeProvider();

                if ($provider === null) {
                    Log::warning('Failed to instantiate provider for plan step.', [
                        'providerClass' => $step->providerClass,
                        'providerId' => $providerModel->id,
                    ]);

                    return new SearchResult(
                        items: [],
                        totalCount: 0,
                        providerName: $step->providerClass,
                    );
                }

                $searchQuery = new SearchQuery(
                    query: $step->parameters['query'] ?? '',
                    namespace: $step->parameters['namespace'] ?? null,
                    maxResults: $step->parameters['maxResults'] ?? 10,
                    filters: $step->parameters['filters'] ?? [],
                    searchType: $step->operation === 'search' ? null : $step->operation,
                );

                return $provider->search($searchQuery);
            };
        })->all();
    }
}
