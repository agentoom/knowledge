<?php

namespace App\Contracts;

interface VectorStore
{
    /**
     * @param  array<string, mixed>  $document
     * @param  array<int, float>|null  $embedding
     */
    public function index(string $collection, string $id, array $document, ?array $embedding = null): void;

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, array<string, mixed>>
     */
    public function search(string $collection, array $query, int $limit = 10): array;

    public function delete(string $collection, string $id): void;

    public function healthCheck(): bool;

    /**
     * @return array<string, mixed>
     */
    public function stats(): array;

    /**
     * @return array<int, string>
     */
    public function capabilities(): array;

    /**
     * @param  array<string, mixed>  $schema
     */
    public function ensureCollectionExists(string $collection, array $schema): void;
}
