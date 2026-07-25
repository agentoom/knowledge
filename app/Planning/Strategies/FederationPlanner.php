<?php

namespace App\Planning\Strategies;

use App\Contracts\PlannerStrategy;
use App\Federation\FederationManager;
use App\Knowledge\Services\MetadataRegistryService;
use App\Retrieval\Models\ExecutionPlan;
use App\Retrieval\Models\PlanStep;
use App\Retrieval\Models\SearchQuery;
use Illuminate\Support\Facades\Log;

/**
 * Extends the default planner with federation awareness.
 *
 * Routes queries to local providers first, then federated servers.
 * Federation providers are given lower priority so local results
 * surface first, but a full namespace scope to 'federation.*' will
 * query only remote servers.
 */
class FederationPlanner implements PlannerStrategy
{
    public function __construct(
        private readonly MetadataRegistryService $registry,
        private readonly FederationManager $federation,
    ) {}

    public function plan(SearchQuery $query): ExecutionPlan
    {
        $registryData = $this->registry->get();
        $localProviders = $registryData['providers'] ?? [];

        $steps = [];
        $basePriority = 10;

        // Local providers first
        $filteredLocal = $this->filterProviders($localProviders, $query);

        usort($filteredLocal, fn ($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

        foreach ($filteredLocal as $provider) {
            $steps[] = new PlanStep(
                providerClass: $provider['class'],
                operation: $this->resolveOperation($query, $provider),
                parameters: [
                    'query' => $query->query,
                    'namespace' => $query->namespace,
                    'maxResults' => $query->maxResults,
                    'filters' => $query->filters,
                ],
                priority: $basePriority,
            );

            $basePriority += 10;
        }

        // Federation providers (lower priority, queried in addition)
        if ($this->shouldQueryFederation($query)) {
            try {
                $federationProviders = $this->federation->getProviders();

                foreach ($federationProviders as $federationProvider) {
                    $steps[] = new PlanStep(
                        providerClass: '__federation__',
                        operation: 'search',
                        parameters: [
                            'query' => $query->query,
                            'namespace' => $query->namespace,
                            'maxResults' => $query->maxResults,
                            'filters' => $query->filters,
                            '_federation_provider' => $federationProvider,
                        ],
                        priority: $basePriority + 50, // Lower priority than local
                    );

                    $basePriority += 10;
                }
            } catch (\Throwable $e) {
                Log::warning('FederationPlanner: failed to build federation steps.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $strategy = $steps !== [] ? 'federation' : 'default';

        return new ExecutionPlan(steps: $steps, strategy: $strategy, query: $query);
    }

    public function name(): string
    {
        return 'federation';
    }

    /**
     * @param  array<int, array<string, mixed>>  $providers
     * @return array<int, array<string, mixed>>
     */
    private function filterProviders(array $providers, SearchQuery $query): array
    {
        if ($query->namespace === null || $query->namespace === '' || $query->namespace === '0') {
            return $providers;
        }

        return array_filter($providers, function (array $provider) use ($query): bool {
            return $provider['namespace'] === $query->namespace
                || in_array($query->namespace, $provider['capabilities'] ?? [], true);
        });
    }

    /**
     * @param  array<string, mixed>  $provider
     */
    private function resolveOperation(SearchQuery $query, array $provider): string
    {
        if ($query->searchType === null || $query->searchType === '' || $query->searchType === '0') {
            return 'search';
        }

        return in_array($query->searchType, $provider['capabilities'] ?? [], true)
            ? $query->searchType
            : 'search';
    }

    private function shouldQueryFederation(SearchQuery $query): bool
    {
        // Always include federation unless explicitly scoped to a local-only namespace
        return $query->namespace === null
            || $query->namespace === ''
            || $query->namespace === '0'
            || str_starts_with($query->namespace ?? '', 'federation.');
    }
}
