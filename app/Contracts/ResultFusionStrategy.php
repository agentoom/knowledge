<?php

namespace App\Contracts;

use App\Retrieval\Fusion\RecencyBoostConfig;
use App\Retrieval\Models\SearchResult;

interface ResultFusionStrategy
{
    /**
     * @param  array<int, SearchResult>  $results
     * @return array<int, array<string, mixed>>
     */
    public function fuse(array $results, ?RecencyBoostConfig $recencyConfig = null): array;

    public function name(): string;
}
