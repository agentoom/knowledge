<?php

namespace App\Retrieval\Models;

use Livewire\Wireable;

class SearchQuery implements Wireable
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public readonly string $query,
        public readonly ?string $namespace = null,
        public readonly int $maxResults = 10,
        public readonly array $filters = [],
        public readonly ?string $searchType = null,
    ) {}

    public function toLivewire(): array
    {
        return [
            'query' => $this->query,
            'namespace' => $this->namespace,
            'maxResults' => $this->maxResults,
            'filters' => $this->filters,
            'searchType' => $this->searchType,
        ];
    }

    public static function fromLivewire($value): static
    {
        return new static(
            query: $value['query'],
            namespace: $value['namespace'],
            maxResults: $value['maxResults'],
            filters: $value['filters'],
            searchType: $value['searchType'],
        );
    }
}
