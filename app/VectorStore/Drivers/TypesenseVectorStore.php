<?php

namespace App\VectorStore\Drivers;

use App\Contracts\VectorStore as VectorStoreContract;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TypesenseVectorStore implements VectorStoreContract
{
    private string $host;

    private string $apiKey;

    public function __construct(?array $config = null)
    {
        $this->host = $config['host'] ?? config('scout.typesense.host', 'http://typesense:8108');
        $this->apiKey = $config['api_key'] ?? config('scout.typesense.api_key', '');

        if (isset($config['port']) && ! str_contains($this->host, ':')) {
            $this->host .= ":{$config['port']}";
        }

        if (isset($config['protocol']) && ! str_starts_with($this->host, 'http')) {
            $this->host = "{$config['protocol']}://{$this->host}";
        }
    }

    public function index(string $collection, string $id, array $document, ?array $embedding = null): void
    {
        $url = "{$this->host}/collections/{$collection}/documents";

        $payload = $document;

        if ($embedding !== null) {
            $payload['embedding'] = $embedding;
        }

        $response = Http::withHeaders([
            'X-TYPESENSE-API-KEY' => $this->apiKey,
        ])->post($url, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Typesense index failed: HTTP {$response->status()} — {$response->body()}"
            );
        }
    }

    public function search(string $collection, array $query, int $limit = 10): array
    {
        $url = "{$this->host}/collections/{$collection}/documents/search";

        $response = Http::withHeaders([
            'X-TYPESENSE-API-KEY' => $this->apiKey,
        ])->get($url, array_merge($this->normalizeVectorQuery($query), ['per_page' => $limit]));

        if (! $response->successful()) {
            Log::error('TypesenseVectorStore: search failed.', [
                'collection' => $collection,
                'status' => $response->status(),
            ]);

            return [];
        }

        return $response->json()['hits'] ?? [];
    }

    public function delete(string $collection, string $id): void
    {
        $url = "{$this->host}/collections/{$collection}/documents/{$id}";

        Http::withHeaders([
            'X-TYPESENSE-API-KEY' => $this->apiKey,
        ])->delete($url);
    }

    /**
     * Drop an entire collection from the vector store.
     */
    public function deleteCollection(string $collection): void
    {
        Http::withHeaders([
            'X-TYPESENSE-API-KEY' => $this->apiKey,
        ])->delete("{$this->host}/collections/{$collection}");
    }

    public function healthCheck(): bool
    {
        try {
            $response = Http::withHeaders([
                'X-TYPESENSE-API-KEY' => $this->apiKey,
            ])->get("{$this->host}/health");

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function stats(): array
    {
        try {
            $response = Http::withHeaders([
                'X-TYPESENSE-API-KEY' => $this->apiKey,
            ])->get("{$this->host}/collections/knowledge_chunks");

            if (! $response->successful()) {
                return ['document_count' => 0];
            }

            return [
                'document_count' => $response->json()['num_documents'] ?? 0,
                'collection_size' => $response->json()['num_documents'] ?? 0,
            ];
        } catch (\Throwable) {
            return ['document_count' => 0];
        }
    }

    public function capabilities(): array
    {
        return [
            'typesense',
            'managed_embeddings',
            'hybrid_search',
            'filtering',
            'faceting',
        ];
    }

    public function ensureCollectionExists(string $collection, array $schema): void
    {
        $response = Http::withHeaders([
            'X-TYPESENSE-API-KEY' => $this->apiKey,
        ])->get("{$this->host}/collections/{$collection}");

        if ($response->successful()) {
            return;
        }

        $schema['name'] = $collection;

        Http::withHeaders([
            'X-TYPESENSE-API-KEY' => $this->apiKey,
        ])->post("{$this->host}/collections", $schema);
    }

    /**
     * Fetch the existing schema of a collection, or null when it does not exist.
     *
     * Used to detect vector-dimension conflicts before indexing client-side
     * embeddings. This is a concrete driver capability; the contract is
     * intentionally left unchanged.
     *
     * @return array<string, mixed>|null
     */
    public function collectionSchema(string $collection): ?array
    {
        $response = Http::withHeaders([
            'X-TYPESENSE-API-KEY' => $this->apiKey,
        ])->get("{$this->host}/collections/{$collection}");

        if (! $response->successful()) {
            return null;
        }

        $schema = $response->json();

        return is_array($schema) ? $schema : null;
    }

    /**
     * Translate a raw query vector (passed as an array under `vector_query`)
     * into Typesense's `vector_query` syntax.
     *
     * String `vector_query` values (managed embeddings) pass through unchanged.
     * An optional `vector_alpha` key carries the hybrid alpha weight.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function normalizeVectorQuery(array $query): array
    {
        $vector = $query['vector_query'] ?? null;

        if (! is_array($vector)) {
            return $query;
        }

        $alpha = $query['vector_alpha'] ?? null;
        unset($query['vector_alpha']);

        $values = implode(',', array_map(
            fn (float $value): string => rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.'),
            $vector
        ));

        $parts = ['embedding:(['.$values.']'];

        if ($alpha !== null) {
            $parts[] = 'alpha: '.number_format((float) $alpha, 1);
        }

        $parts[] = 'k: 25';

        $query['vector_query'] = implode(', ', $parts).')';

        return $query;
    }
}
