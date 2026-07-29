<?php

namespace App\Retrieval\Services;

use App\Contracts\RemoteProvider;
use App\Contracts\ResultFusionStrategy;
use App\Events\RetrievalExecuted;
use App\Knowledge\Services\ProviderManager;
use App\Models\RetrievalLog;
use App\Retrieval\Fusion\RecencyBoostConfig;
use App\Retrieval\Models\ExecutionPlan;
use App\Retrieval\Models\PlanStep;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use App\Settings\Facades\Settings;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Http;
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

        // Split steps: local providers run in-process, remote providers use Http::pool()
        [$localSteps, $remoteSteps] = $this->splitSteps($plan->steps);

        $results = [];

        // 1. Execute remote (federation) providers concurrently via Http::pool()
        if ($remoteSteps !== []) {
            $results = array_merge($results, $this->executeRemoteSteps($remoteSteps));
        }

        // 2. Execute local providers in-process (single provider skips Concurrency overhead)
        if ($localSteps !== []) {
            $results = array_merge($results, $this->executeLocalSteps($localSteps));
        }

        $fused = $this->fusion->fuse($results, $this->buildRecencyConfig());

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
    private function buildRecencyConfig(): ?RecencyBoostConfig
    {
        $enabled = (bool) Settings::get('knowledge.recency_boost_enabled', false);

        if (! $enabled) {
            return null;
        }

        return new RecencyBoostConfig(
            boostFactor: (float) Settings::get('knowledge.recency_boost_factor', 0.3),
            halfLifeDays: (float) Settings::get('knowledge.recency_boost_half_life_days', 30.0),
        );
    }

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
     * Split plan steps into local (in-process) and remote (HTTP-callable) groups.
     *
     * @param  array<int, PlanStep>  $steps
     * @return array{0: array<int, PlanStep>, 1: array<int, array{step: PlanStep, provider: RemoteProvider}>}
     */
    private function splitSteps(array $steps): array
    {
        $local = [];
        $remote = [];

        foreach ($steps as $step) {
            if ($step->providerClass === '__federation__') {
                $federationProvider = $step->parameters['_federation_provider'] ?? null;

                if ($federationProvider instanceof RemoteProvider) {
                    $remote[] = ['step' => $step, 'provider' => $federationProvider];

                    continue;
                }
            }

            $local[] = $step;
        }

        return [$local, $remote];
    }

    /**
     * Execute remote (federation) providers concurrently via Http::pool().
     *
     * @param  array<int, array{step: PlanStep, provider: RemoteProvider}>  $remoteSteps
     * @return array<int, SearchResult>
     */
    private function executeRemoteSteps(array $remoteSteps): array
    {
        if ($remoteSteps === []) {
            return [];
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($remoteSteps) {
                $requests = [];

                foreach ($remoteSteps as $i => ['step' => $step, 'provider' => $provider]) {
                    $searchQuery = $this->buildSearchQuery($step);

                    $requests[] = $pool->as((string) $i)
                        ->withToken($provider->getAuthToken())
                        ->withHeaders(['Content-Type' => 'application/json'])
                        ->timeout(30)
                        ->post($provider->getEndpointUrl(), $provider->buildSearchPayload($searchQuery));
                }

                return $requests;
            });
        } catch (\Throwable $e) {
            Log::warning('RetrievalEngine: Http::pool() failed for federation steps.', [
                'error' => $e->getMessage(),
                'server_count' => count($remoteSteps),
            ]);

            // Fall back to empty results for all remote steps
            return array_map(function (array $rs): SearchResult {
                return new SearchResult(
                    items: [],
                    totalCount: 0,
                    providerName: 'federation.'.$rs['provider']->getServerName(),
                );
            }, $remoteSteps);
        }

        $results = [];

        foreach ($remoteSteps as $i => ['provider' => $provider]) {
            $response = $responses[$i] ?? null;

            if ($response === null || ! $response->ok()) {
                Log::warning('RetrievalEngine: federation response failed.', [
                    'server' => $provider->getServerName(),
                    'status' => $response?->status(),
                ]);

                $results[] = new SearchResult(
                    items: [],
                    totalCount: 0,
                    providerName: 'federation.'.$provider->getServerName(),
                );

                continue;
            }

            try {
                $body = $response->json();
                $results[] = $provider->parseSearchResponse($body, $provider->getServerName());
            } catch (\Throwable $e) {
                Log::warning('RetrievalEngine: failed to parse federation response.', [
                    'server' => $provider->getServerName(),
                    'error' => $e->getMessage(),
                ]);

                $results[] = new SearchResult(
                    items: [],
                    totalCount: 0,
                    providerName: 'federation.'.$provider->getServerName(),
                );
            }
        }

        return $results;
    }

    /**
     * Execute local providers in-process.
     *
     * @param  array<int, PlanStep>  $localSteps
     * @return array<int, SearchResult>
     */
    private function executeLocalSteps(array $localSteps): array
    {
        $callbacks = array_map(function ($step) {
            return function () use ($step): SearchResult {
                try {
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

                    $searchQuery = $this->buildSearchQuery($step);

                    return $provider->search($searchQuery);
                } catch (\Throwable $e) {
                    Log::warning('Provider search failed for plan step.', [
                        'providerClass' => $step->providerClass,
                        'error' => $e->getMessage(),
                    ]);

                    return new SearchResult(
                        items: [],
                        totalCount: 0,
                        providerName: $step->providerClass,
                    );
                }
            };
        }, $localSteps);

        if ($callbacks === []) {
            return [];
        }

        // Skip process-spawning overhead when there is only one provider.
        if (count($callbacks) === 1) {
            return [$callbacks[0]()];
        }

        return Concurrency::run($callbacks);
    }

    /**
     * Build a SearchQuery from a PlanStep's parameters.
     */
    private function buildSearchQuery($step): SearchQuery
    {
        return new SearchQuery(
            query: $step->parameters['query'] ?? '',
            namespace: $step->parameters['namespace'] ?? null,
            maxResults: $step->parameters['maxResults'] ?? 10,
            filters: $step->parameters['filters'] ?? [],
            searchType: $step->operation === 'search' ? null : $step->operation,
        );
    }
}
