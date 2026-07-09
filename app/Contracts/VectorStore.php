<?php

namespace App\Contracts;

interface VectorStore
{
    public function index(string $collection, string $id, array $document, ?array $embedding = null): void;

    public function search(string $collection, array $query, int $limit = 10): array;

    public function delete(string $collection, string $id): void;

    public function healthCheck(): bool;

    public function stats(): array;

    public function capabilities(): array;

    public function ensureCollectionExists(string $collection, array $schema): void;
}
