<?php

namespace App\Knowledge\Models;

class ProviderMetadata
{
    /**
     * @param  array<int, string>  $capabilities
     * @param  array<int, string>  $searchableResources
     * @param  array<int, string>  $searchableFields
     * @param  array<int, string>  $relationships
     * @param  array<int, string>  $supportedOperations
     */
    public function __construct(
        public readonly array $capabilities = [],
        public readonly array $searchableResources = [],
        public readonly array $searchableFields = [],
        public readonly array $relationships = [],
        public readonly string $namespace = '',
        public readonly array $supportedOperations = [],
    ) {}
}
