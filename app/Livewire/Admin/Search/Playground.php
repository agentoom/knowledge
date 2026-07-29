<?php

namespace App\Livewire\Admin\Search;

use App\Knowledge\Services\MetadataRegistryService;
use App\Planning\Services\QueryPlanner;
use App\Retrieval\Models\ExecutionPlan;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use App\Retrieval\Services\RetrievalEngine;
use Livewire\Component;

class Playground extends Component
{
    public string $query = '';

    public ?string $namespace = null;

    public ?string $searchType = 'hybrid';

    public bool $abMode = false;

    public ?string $searchTypeB = '';

    public ?ExecutionPlan $plan = null;

    public ?SearchResult $result = null;

    /** @var array{plan: ?ExecutionPlan, result: ?SearchResult, latency: float}|null */
    public ?array $sideB = null;

    public bool $isSearching = false;

    public float $latency = 0;

    /** @var array<string, string> */
    public array $searchTypeOptions = [
        'hybrid' => 'Hybrid (keyword + vector)',
        'semantic' => 'Vector-only (semantic)',
        '' => 'Keyword-only (text match)',
    ];

    /**
     * When Side A changes in A/B mode, bump Side B to a different value if they collide.
     */
    public function updatedSearchType(string $value): void
    {
        if (! $this->abMode || ! array_key_exists($value, $this->searchTypeOptions)) {
            return;
        }

        if ($value === $this->searchTypeB) {
            $this->searchTypeB = $this->pickAlternative($value);
            $this->dispatch('notify', message: __('Side A selection bumped Side B to :option.', ['option' => $this->searchTypeOptions[$this->searchTypeB]]));
        }
    }

    /**
     * When Side B changes in A/B mode, bump Side A to a different value if they collide.
     */
    public function updatedSearchTypeB(string $value): void
    {
        if (! $this->abMode || ! array_key_exists($value, $this->searchTypeOptions)) {
            return;
        }

        if ($value === $this->searchType) {
            $this->searchType = $this->pickAlternative($value);
            $this->dispatch('notify', message: __('Compare vs selection bumped Side A to :option.', ['option' => $this->searchTypeOptions[$this->searchType]]));
        }
    }

    /**
     * Pick an option that is not the excluded value.
     */
    private function pickAlternative(string $exclude): string
    {
        foreach ($this->searchTypeOptions as $value => $label) {
            if ($value !== $exclude) {
                return $value;
            }
        }

        return $exclude;
    }

    public function search(QueryPlanner $planner, RetrievalEngine $engine): void
    {
        if (empty(trim($this->query))) {
            return;
        }

        $this->isSearching = true;

        // Side A — primary search type
        $searchQueryA = new SearchQuery(
            query: $this->query,
            namespace: $this->namespace === '' ? null : $this->namespace,
            maxResults: 10,
            searchType: $this->searchType === 'hybrid' ? null : $this->searchType,
        );

        $start = microtime(true);
        $this->plan = $planner->plan($searchQueryA);
        $this->result = $engine->execute($this->plan);
        $this->latency = (microtime(true) - $start) * 1000;

        // Side B — comparison search type (AB mode)
        if ($this->abMode && $this->searchTypeB !== $this->searchType) {
            $searchQueryB = new SearchQuery(
                query: $this->query,
                namespace: $this->namespace === '' ? null : $this->namespace,
                maxResults: 10,
                searchType: $this->searchTypeB === 'hybrid' ? null : $this->searchTypeB,
            );

            $startB = microtime(true);
            $planB = $planner->plan($searchQueryB);
            $resultB = $engine->execute($planB);
            $latencyB = (microtime(true) - $startB) * 1000;

            $this->sideB = [
                'plan' => $planB,
                'result' => $resultB,
                'latency' => $latencyB,
            ];
        } else {
            $this->sideB = null;
        }

        $this->isSearching = false;
    }

    public function render(MetadataRegistryService $registry)
    {
        $namespaces = collect($registry->get()['providers'] ?? [])
            ->pluck('namespace')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return view('livewire.admin.search.playground', [
            'namespaces' => $namespaces,
        ])->layout('layouts.app', ['header' => 'Search Playground']);
    }
}
