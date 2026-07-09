# Extending Agentoom Knowledge

This guide explains how to add custom knowledge providers, chunking strategies, vector stores, and planner strategies to Agentoom Knowledge.

---

## Providers

### Creating a Custom Knowledge Provider

A Knowledge Provider is the bridge between Agentoom and a data source. Implement the `KnowledgeProvider` contract:

```php
<?php

namespace App\Providers\Custom;

use App\Contracts\KnowledgeProvider;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;

class MyCustomProvider implements KnowledgeProvider
{
    public function __construct(
        private readonly string $apiEndpoint,
        private readonly string $apiToken,
    ) {}

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            capabilities: ['search', 'list_resources'],
            searchableResources: ['my_resource'],
            searchableFields: ['title', 'body', 'tags'],
            namespace: 'my_custom',
            supportedOperations: ['full_text'],
        );
    }

    public function search(SearchQuery $query): SearchResult
    {
        // Your search implementation here
        $results = $this->callApi($query->query);

        return new SearchResult(
            items: $results,
            totalCount: count($results),
            providerName: 'my_custom',
        );
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, $this->metadata()->supportedOperations, true);
    }

    private function callApi(string $query): array
    {
        // Call your API, database, or file system
        return [];
    }
}
```

### Discovery Methods

Your provider can optionally expose a `discoverFiles()` method for the document pipeline:

```php
public function discoverFiles(): Collection
{
    return collect($this->listFromApi())->map(fn ($item) => [
        'path'       => $item['url'],
        'filename'   => $item['name'],
        'size'       => $item['bytes'] ?? 0,
    ]);
}
```

### Registering

Run the generator command to scaffold and register a provider:

```bash
vendor/bin/sail artisan make:knowledge-provider MyCustom
```

Or register manually by adding to `config/knowledge.php`:

```php
'providers' => [
    'my_custom' => App\Providers\Custom\MyCustomProvider::class,
],
```

---

## Chunking Strategies

### Creating a Custom Strategy

Implement the `ChunkingStrategy` contract:

```php
<?php

namespace App\DocumentPipeline\Chunking;

use App\Contracts\ChunkingStrategy;

class TokenAwareChunking implements ChunkingStrategy
{
    public function chunk(string $content, array $options = []): array
    {
        $maxTokens = $options['max_tokens'] ?? 512;

        // Split by estimated tokens, respecting paragraph boundaries
        $chunks = [];
        $current = '';

        foreach (explode("\n\n", $content) as $paragraph) {
            $tokenCount = (int) (str_word_count($paragraph) * 1.3);

            if ($tokenCount > $maxTokens) {
                // Sub-split large paragraphs
                $chunks = array_merge($chunks, $this->splitByTokens($paragraph, $maxTokens));
            } elseif (str_word_count($current.' '.$paragraph) * 1.3 <= $maxTokens) {
                $current .= ($current ? "\n\n" : '').$paragraph;
            } else {
                $chunks[] = $current;
                $current = $paragraph;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    public function name(): string
    {
        return 'token_aware';
    }

    private function splitByTokens(string $text, int $maxTokens): array
    {
        // Token-aware sentence splitting
        return [];
    }
}
```

### Registering

Add your strategy to `ChunkingStrategyRegistry::resolve()`.

---

## Vector Stores

### Creating a Custom Vector Store Driver

Implement the `VectorStore` contract:

```php
<?php

namespace App\VectorStore\Drivers;

use App\Contracts\VectorStore as VectorStoreContract;

class PineconeVectorStore implements VectorStoreContract
{
    public function index(string $collection, string $id, array $document, ?array $embedding = null): void {}
    public function search(string $collection, array $query, int $limit = 10): array { return []; }
    public function delete(string $collection, string $id): void {}
    public function healthCheck(): bool { return true; }
    public function stats(): array { return ['document_count' => 0]; }
    public function capabilities(): array { return ['managed_embeddings' => false, 'driver' => 'pinecone']; }
    public function ensureCollectionExists(string $collection, array $schema): void {}
}
```

Add to `VectorStoreManager::resolve()`:

```php
'pinecone' => new PineconeVectorStore($config),
```

---

## Planner Strategies

### Creating a Custom Planner

Implement the `PlannerStrategy` contract:

```php
<?php

namespace App\Planning\Strategies;

use App\Contracts\PlannerStrategy;
use App\Retrieval\Models\ExecutionPlan;
use App\Retrieval\Models\PlanStep;
use App\Retrieval\Models\SearchQuery;

class CostAwarePlanner implements PlannerStrategy
{
    public function plan(SearchQuery $query): ExecutionPlan
    {
        // Custom routing logic — prefer local providers
        return new ExecutionPlan(steps: [], strategy: 'cost_aware', query: $query);
    }

    public function name(): string
    {
        return 'cost_aware';
    }
}
```

---

## Architecture Overview

```
app/
├── Contracts/           ← All extension interfaces
│   ├── ChunkingStrategy.php
│   ├── DocumentParser.php
│   ├── EmbeddingProvider.php
│   ├── KnowledgeProvider.php
│   ├── PlannerStrategy.php
│   ├── ResultFusionStrategy.php
│   ├── SettingsManager.php
│   └── VectorStore.php
├── Providers/           ← Built-in providers (extend here)
│   ├── Filesystem/
│   ├── Json/
│   ├── Sql/
│   ├── VectorStore/
│   ├── Web/
│   └── Yaml/
├── DocumentPipeline/    ← Chunking and parsing
│   ├── Chunking/
│   ├── Parsers/
│   └── Services/
├── Planning/            ← Query planner and strategies
├── Retrieval/           ← Execution engine and fusion
└── VectorStore/         ← Vector store drivers
```

All extension points use Laravel's service container — custom providers receive their configuration via constructor injection when created by the `ProviderManager`.
