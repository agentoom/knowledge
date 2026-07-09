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

    public ?ExecutionPlan $plan = null;

    public ?SearchResult $result = null;

    public bool $isSearching = false;

    public float $latency = 0;

    public function search(QueryPlanner $planner, RetrievalEngine $engine): void
    {
        if (empty(trim($this->query))) {
            return;
        }

        $this->isSearching = true;

        $searchQuery = new SearchQuery(
            query: $this->query,
            namespace: $this->namespace === '' ? null : $this->namespace,
            maxResults: 10,
            searchType: $this->searchType === 'hybrid' ? null : $this->searchType,
        );

        $start = microtime(true);

        $this->plan = $planner->plan($searchQuery);
        $this->result = $engine->execute($this->plan);

        $this->latency = (microtime(true) - $start) * 1000;
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
