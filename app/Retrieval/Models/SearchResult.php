<?php

namespace App\Retrieval\Models;

use Livewire\Wireable;

class SearchResult implements Wireable
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly array $items = [],
        public readonly int $totalCount = 0,
        public readonly string $providerName = '',
        public readonly array $metadata = [],
    ) {}

    public function toLivewire(): array
    {
        return [
            'items' => $this->items,
            'totalCount' => $this->totalCount,
            'providerName' => $this->providerName,
            'metadata' => $this->metadata,
        ];
    }

    public static function fromLivewire($value): static
    {
        return new static(
            items: $value['items'],
            totalCount: $value['totalCount'],
            providerName: $value['providerName'],
            metadata: $value['metadata'],
        );
    }
}
