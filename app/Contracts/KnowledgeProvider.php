<?php

namespace App\Contracts;

use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;

interface KnowledgeProvider
{
    public function metadata(): ProviderMetadata;

    public function search(SearchQuery $query): SearchResult;

    public function supports(string $operation): bool;
}
