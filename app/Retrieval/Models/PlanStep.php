<?php

namespace App\Retrieval\Models;

use Livewire\Wireable;

class PlanStep implements Wireable
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public readonly string $providerClass,
        public readonly string $operation,
        public readonly array $parameters = [],
        public readonly int $priority = 0,
    ) {}

    public function toLivewire(): array
    {
        return [
            'providerClass' => $this->providerClass,
            'operation' => $this->operation,
            'parameters' => $this->parameters,
            'priority' => $this->priority,
        ];
    }

    public static function fromLivewire($value): static
    {
        return new static(
            providerClass: $value['providerClass'],
            operation: $value['operation'],
            parameters: $value['parameters'],
            priority: $value['priority'],
        );
    }
}
