<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RetrievalExecuted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $query,
        public readonly int $resultCount,
        public readonly float $durationMs,
        public readonly int $providersQueried = 0,
    ) {}
}
