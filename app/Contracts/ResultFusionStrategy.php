<?php

namespace App\Contracts;

use App\Retrieval\Models\SearchResult;

interface ResultFusionStrategy
{
    /**
     * @param  array<int, SearchResult>  $results
     * @return array<int, SearchResult>
     */
    public function fuse(array $results): array;

    public function name(): string;
}
