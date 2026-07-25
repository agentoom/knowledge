<?php

namespace App\Planning\Strategies;

use App\Contracts\PlannerStrategy;
use App\Knowledge\Services\MetadataRegistryService;
use App\Retrieval\Models\ExecutionPlan;
use App\Retrieval\Models\PlanStep;
use App\Retrieval\Models\SearchQuery;

class DefaultPlanner implements PlannerStrategy
{
    public function __construct(
        private readonly MetadataRegistryService $registry,
    ) {}

    public function plan(SearchQuery $query): ExecutionPlan
    {
        $registryData = $this->registry->get();

        $providers = $registryData['providers'] ?? [];

        if (empty($providers)) {
            return new ExecutionPlan(steps: [], strategy: 'default', query: $query);
        }

        $filteredProviders = $providers;

        if ($query->namespace !== null && $query->namespace !== '' && $query->namespace !== '0') {
            $filteredProviders = array_filter($providers, function (array $provider) use ($query): bool {
                return $provider['namespace'] === $query->namespace
                    || in_array($query->namespace, $provider['capabilities'] ?? [], true);
            });
        }

        // Sort providers by priority (highest first)
        usort($filteredProviders, fn ($a, $b) => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

        $steps = [];
        $priority = 10;

        foreach ($filteredProviders as $provider) {
            $operation = 'search';

            if ($query->searchType !== null && $query->searchType !== '' && $query->searchType !== '0') {
                $operation = in_array($query->searchType, $provider['capabilities'] ?? [], true)
                    ? $query->searchType
                    : 'search';
            }

            $steps[] = new PlanStep(
                providerClass: $provider['class'],
                operation: $operation,
                parameters: [
                    'query' => $query->query,
                    'namespace' => $query->namespace,
                    'maxResults' => $query->maxResults,
                    'filters' => $query->filters,
                ],
                priority: $priority,
            );

            $priority += 10;
        }

        return new ExecutionPlan(steps: $steps, strategy: 'default', query: $query);
    }

    public function name(): string
    {
        return 'default';
    }
}
