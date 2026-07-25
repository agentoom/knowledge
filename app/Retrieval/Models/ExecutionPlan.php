<?php

namespace App\Retrieval\Models;

use Livewire\Wireable;

class ExecutionPlan implements Wireable
{
    /**
     * @param  array<int, PlanStep>  $steps
     */
    public function __construct(
        public readonly array $steps = [],
        public readonly string $strategy = '',
        public readonly ?SearchQuery $query = null,
    ) {}

    public function toLivewire(): array
    {
        return [
            'steps' => $this->steps,
            'strategy' => $this->strategy,
            'query' => $this->query,
        ];
    }

    public static function fromLivewire($value): static
    {
        return new static(
            steps: $value['steps'],
            strategy: $value['strategy'],
            query: $value['query'],
        );
    }
}
