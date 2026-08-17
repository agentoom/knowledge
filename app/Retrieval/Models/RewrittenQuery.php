<?php

namespace App\Retrieval\Models;

/**
 * Immutable result of query expansion.
 *
 * Carries the original query, the expanded form, and the decomposed term
 * lists so providers can run a weighted second pass: original-term matches
 * outrank synonym-only matches.
 *
 * @property-read array<int, string> $originalTerms
 * @property-read array<int, string> $expansionTerms
 */
final class RewrittenQuery
{
    /**
     * @param  array<int, string>  $originalTerms
     * @param  array<int, string>  $expansionTerms
     */
    public function __construct(
        public readonly string $original,
        public readonly string $rewritten,
        public readonly array $originalTerms = [],
        public readonly array $expansionTerms = [],
    ) {}

    public function hasExpansion(): bool
    {
        return $this->original !== $this->rewritten && $this->expansionTerms !== [];
    }
}
