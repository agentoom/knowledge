<?php

namespace App\Contracts;

interface EmbeddingProvider
{
    /**
     * Embed a single text into a float vector.
     *
     * Providers that distinguish document vs. query embeddings use
     * `search_document` for indexing and `search_query` for query vectors.
     *
     * @param  'search_document'|'search_query'  $inputType
     * @return array<int, float>
     *
     * @throws \RuntimeException when the provider is unreachable, malformed,
     *                           or returns a vector whose dimension mismatches `dimensions()`.
     */
    public function embed(string $text, string $inputType = 'search_document'): array;

    /**
     * The fixed dimensionality of the vectors produced by this provider.
     */
    public function dimensions(): int;
}
