<?php

namespace App\Embedding\Providers;

use App\Contracts\EmbeddingProvider;
use LogicException;

/**
 * Represents the managed-embedding mode of the vector store (Typesense).
 *
 * No external embedding service is involved: the vector store computes
 * vectors internally from the document text at index/query time. Calling
 * `embed()` is a programming error — callers must branch on the active
 * vector store's `managed_embeddings` capability instead.
 */
class TypesenseProvider implements EmbeddingProvider
{
    public function embed(string $text, string $inputType = 'search_document'): array
    {
        throw new LogicException(
            'The typesense embedding provider manages embeddings inside the vector store; external embedding is not available. '
            .'Select a different provider (knowledge.embedding_provider) to compute vectors client-side.'
        );
    }

    public function dimensions(): int
    {
        throw new LogicException(
            'The typesense embedding provider has no client-side dimension; embeddings are managed by the vector store.'
        );
    }
}
