<?php

use App\Contracts\EmbeddingProvider;
use App\Contracts\VectorStore;
use App\Embedding\Services\EmbeddingManager;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Providers\VectorStore\SemanticProvider;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Services\SynonymService;
use App\Settings\Facades\Settings;
use App\VectorStore\Services\VectorStoreManager;

/**
 * Records every search call and returns canned hits keyed by the q parameter.
 */
class RecordingVectorDriver implements VectorStore
{
    /** @var array<int, array{collection: string, query: array<string, mixed>, limit: int}> */
    public array $searches = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $hitsByQuery = [];

    public array $capabilities = [];

    public function index(string $collection, string $id, array $document, ?array $embedding = null): void {}

    public function search(string $collection, array $query, int $limit = 10): array
    {
        $this->searches[] = [
            'collection' => $collection,
            'query' => $query,
            'limit' => $limit,
        ];

        $key = (string) ($query['q'] ?? '*');

        return $this->hitsByQuery[$key] ?? [];
    }

    public function delete(string $collection, string $id): void {}

    public function deleteCollection(string $collection): void {}

    public function healthCheck(): bool
    {
        return true;
    }

    public function stats(): array
    {
        return ['document_count' => 0];
    }

    public function capabilities(): array
    {
        return $this->capabilities;
    }

    public function ensureCollectionExists(string $collection, array $schema): void {}
}

/**
 * Build a canned Typesense-style hit for a chunk.
 *
 * @return array<string, mixed>
 */
function chunkHit(int $chunkId, int $documentId, string $content = 'content', int $textMatch = 10): array
{
    return [
        'document' => [
            'chunk_id' => (string) $chunkId,
            'document_id' => (string) $documentId,
            'content' => $content,
            'document_filename' => 'doc-'.$documentId.'.md',
            'namespace' => 'docs',
            'created_at' => '2026-01-01T00:00:00Z',
        ],
        'text_match' => $textMatch,
    ];
}

beforeEach(function () {
    Settings::set('knowledge.synonym_expansion_enabled', true, 'boolean');
    Settings::set('knowledge.synonym_weighting_enabled', false, 'boolean');
    Settings::set('knowledge.synonym_penalty_factor', 0.5, 'float');

    app(SynonymService::class)->create(['car', 'automobile', 'vehicle']);
});

/**
 * Build a SemanticProvider bound to a recording driver and an embedding
 * manager mock. Returns [$provider, $driver, $recorder] where the recorder's
 * ->calls array accumulates the text passed to embed().
 *
 * @return array{0: SemanticProvider, 1: RecordingVectorDriver, 2: stdClass}
 */
function weightedSemanticProvider(bool $managed, array $capabilities): array
{
    $driver = new RecordingVectorDriver;
    $driver->capabilities = $capabilities;

    $manager = Mockery::mock(VectorStoreManager::class);
    $manager->shouldReceive('driver')->andReturn($driver);

    $recorder = new stdClass;
    $recorder->calls = [];

    $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
    $embeddingProvider->shouldReceive('embed')
        ->andReturnUsing(function (string $text) use ($recorder): array {
            $recorder->calls[] = $text;

            return [0.1, 0.2, 0.3];
        });
    $embeddingProvider->shouldReceive('dimensions')->andReturn(3);

    $embeddingManager = Mockery::mock(EmbeddingManager::class);
    $embeddingManager->shouldReceive('isManaged')->andReturn($managed);
    $embeddingManager->shouldReceive('provider')->andReturn($embeddingProvider);

    $provider = new SemanticProvider($manager);

    app()->instance(EmbeddingManager::class, $embeddingManager);

    return [$provider, $driver, $recorder];
}

test('weighting performs two driver searches only when enabled', function () {
    Settings::set('knowledge.synonym_weighting_enabled', true, 'boolean');

    [$provider, $driver] = weightedSemanticProvider(false, []);

    $source = KnowledgeSource::factory()->create(['namespace' => 'docs']);
    Document::factory()->create(['knowledge_source_id' => $source->id, 'status' => 'indexed']);

    $driver->hitsByQuery['car'] = [chunkHit(1, 1)];
    $driver->hitsByQuery['car automobile vehicle'] = [chunkHit(1, 1), chunkHit(2, 1)];

    $result = $provider->search(new SearchQuery(query: 'car', maxResults: 10));

    expect(count($driver->searches))->toBe(2)
        ->and($driver->searches[0]['query']['q'])->toBe('car')
        ->and($driver->searches[1]['query']['q'])->toBe('car automobile vehicle');

    Settings::set('knowledge.synonym_weighting_enabled', false, 'boolean');

    $manager = Mockery::mock(VectorStoreManager::class);
    $manager->shouldReceive('driver')->andReturn($driver);

    $providerDisabled = new SemanticProvider($manager);
    $providerDisabled->search(new SearchQuery(query: 'car', maxResults: 10));

    expect(count($driver->searches))->toBe(3);
});

test('original query candidates outrank synonym-only candidates', function () {
    Settings::set('knowledge.synonym_weighting_enabled', true, 'boolean');

    [$provider, $driver] = weightedSemanticProvider(false, []);

    $source = KnowledgeSource::factory()->create(['namespace' => 'docs']);
    Document::factory()->count(2)->create(['knowledge_source_id' => $source->id, 'status' => 'indexed']);

    // Pass A (original "car") only matches chunk 1; pass B (rewritten) also
    // matches chunk 2 via the synonym "automobile".
    $driver->hitsByQuery['car'] = [chunkHit(1, 1, 'original match', 80)];
    $driver->hitsByQuery['car automobile vehicle'] = [
        chunkHit(1, 1, 'original match', 80),
        chunkHit(2, 2, 'synonym-only match', 90),
    ];

    $result = $provider->search(new SearchQuery(query: 'car', maxResults: 10));

    expect($result->items)->toHaveCount(2)
        ->and($result->items[0]['chunk_id'])->toBe('1')
        ->and($result->items[0]['score'])->toBe(80)
        ->and($result->items[1]['chunk_id'])->toBe('2')
        ->and($result->items[1]['score'])->toBe(45.0); // 90 * 0.5 penalty
});

test('penalty factor is configurable', function () {
    Settings::set('knowledge.synonym_weighting_enabled', true, 'boolean');
    Settings::set('knowledge.synonym_penalty_factor', 0.25, 'float');

    [$provider, $driver] = weightedSemanticProvider(false, []);

    $source = KnowledgeSource::factory()->create(['namespace' => 'docs']);
    Document::factory()->count(2)->create(['knowledge_source_id' => $source->id, 'status' => 'indexed']);

    $driver->hitsByQuery['car'] = [chunkHit(1, 1, 'original', 60)];
    $driver->hitsByQuery['car automobile vehicle'] = [chunkHit(1, 1, 'original', 60), chunkHit(2, 2, 'synonym only', 100)];

    $result = $provider->search(new SearchQuery(query: 'car', maxResults: 10));

    expect($result->items[1]['score'])->toBe(25.0); // 100 * 0.25
});

test('recall pool is bounded between 50 and 250', function () {
    Settings::set('knowledge.synonym_weighting_enabled', true, 'boolean');

    [$provider, $driver] = weightedSemanticProvider(false, []);

    $source = KnowledgeSource::factory()->create(['namespace' => 'docs']);
    Document::factory()->create(['knowledge_source_id' => $source->id, 'status' => 'indexed']);

    $driver->hitsByQuery['car'] = [];
    $driver->hitsByQuery['car automobile vehicle'] = [];

    $provider->search(new SearchQuery(query: 'car', maxResults: 10));

    expect($driver->searches[0]['limit'])->toBe(50)
        ->and($driver->searches[1]['limit'])->toBe(50);

    $driver->searches = [];

    $provider->search(new SearchQuery(query: 'car', maxResults: 200));

    expect($driver->searches[0]['limit'])->toBe(250)
        ->and($driver->searches[1]['limit'])->toBe(250);
});

test('result set is truncated to max results after merging', function () {
    Settings::set('knowledge.synonym_weighting_enabled', true, 'boolean');

    [$provider, $driver] = weightedSemanticProvider(false, []);

    $source = KnowledgeSource::factory()->create(['namespace' => 'docs']);
    Document::factory()->count(5)->create(['knowledge_source_id' => $source->id, 'status' => 'indexed']);

    $driver->hitsByQuery['car'] = [chunkHit(1, 1), chunkHit(2, 2), chunkHit(3, 3)];
    $driver->hitsByQuery['car automobile vehicle'] = [chunkHit(1, 1), chunkHit(4, 4), chunkHit(5, 5)];

    $result = $provider->search(new SearchQuery(query: 'car', maxResults: 3));

    expect($result->items)->toHaveCount(3)
        ->and(collect($result->items)->pluck('chunk_id')->all())->toBe(['1', '2', '3']);
});

test('disabled weighting keeps the legacy single-pass behavior', function () {
    [$provider, $driver] = weightedSemanticProvider(false, []);

    $source = KnowledgeSource::factory()->create(['namespace' => 'docs']);
    Document::factory()->create(['knowledge_source_id' => $source->id, 'status' => 'indexed']);

    $driver->hitsByQuery['car automobile vehicle'] = [chunkHit(1, 1)];

    $result = $provider->search(new SearchQuery(query: 'car', maxResults: 10));

    expect(count($driver->searches))->toBe(1)
        ->and($driver->searches[0]['query']['q'])->toBe('car automobile vehicle')
        ->and($driver->searches[0]['limit'])->toBe(10)
        ->and($result->items[0]['score'])->toBe(10);
});

test('no-match expansion keeps a single pass on the original query', function () {
    Settings::set('knowledge.synonym_weighting_enabled', true, 'boolean');

    [$provider, $driver] = weightedSemanticProvider(false, []);

    $source = KnowledgeSource::factory()->create(['namespace' => 'docs']);
    Document::factory()->create(['knowledge_source_id' => $source->id, 'status' => 'indexed']);

    $driver->hitsByQuery['unique phrase'] = [chunkHit(1, 1)];

    $result = $provider->search(new SearchQuery(query: 'unique phrase', maxResults: 10));

    expect(count($driver->searches))->toBe(1)
        ->and($driver->searches[0]['query']['q'])->toBe('unique phrase');
});

test('managed hybrid weighting uses the real pass query as the keyword q', function () {
    Settings::set('knowledge.synonym_weighting_enabled', true, 'boolean');

    [$provider, $driver] = weightedSemanticProvider(true, ['managed_embeddings']);

    $source = KnowledgeSource::factory()->create(['namespace' => 'docs']);
    Document::factory()->create(['knowledge_source_id' => $source->id, 'status' => 'indexed']);

    $driver->hitsByQuery['car'] = [];
    $driver->hitsByQuery['car automobile vehicle'] = [];

    $provider->search(new SearchQuery(query: 'car', searchType: 'hybrid', maxResults: 10));

    expect(count($driver->searches))->toBe(2);

    [$passA, $passB] = $driver->searches;

    // Pass A keeps the original query as the keyword half, not match-all.
    expect($passA['query']['q'])->toBe('car')
        ->and($passA['query']['vector_query'])->toContain('query: "car"');

    expect($passB['query']['q'])->toBe('car automobile vehicle')
        ->and($passB['query']['vector_query'])->toContain('query: "car automobile vehicle"');
});

test('external hybrid weighting embeds the corresponding pass text', function () {
    Settings::set('knowledge.synonym_weighting_enabled', true, 'boolean');

    [$provider, $driver, $recorder] = weightedSemanticProvider(false, []);

    $source = KnowledgeSource::factory()->create(['namespace' => 'docs']);
    Document::factory()->create(['knowledge_source_id' => $source->id, 'status' => 'indexed']);

    $driver->hitsByQuery['car'] = [];
    $driver->hitsByQuery['car automobile vehicle'] = [];

    $provider->search(new SearchQuery(query: 'car', searchType: 'hybrid', maxResults: 10));

    expect($recorder->calls)->toBe(['car', 'car automobile vehicle']);

    // Each pass carries its own vector and keeps the keyword q.
    expect($driver->searches[0]['query']['q'])->toBe('car')
        ->and($driver->searches[0]['query']['vector_query'])->toBe([0.1, 0.2, 0.3])
        ->and($driver->searches[1]['query']['q'])->toBe('car automobile vehicle');
});

test('external semantic weighting embeds the pass text with a match-all q', function () {
    Settings::set('knowledge.synonym_weighting_enabled', true, 'boolean');

    [$provider, $driver, $recorder] = weightedSemanticProvider(false, []);

    $source = KnowledgeSource::factory()->create(['namespace' => 'docs']);
    Document::factory()->create(['knowledge_source_id' => $source->id, 'status' => 'indexed']);

    $driver->hitsByQuery['*'] = [];

    $provider->search(new SearchQuery(query: 'car', searchType: 'semantic', maxResults: 10));

    expect($recorder->calls)->toBe(['car', 'car automobile vehicle'])
        ->and($driver->searches[0]['query']['q'])->toBe('*')
        ->and($driver->searches[0]['query']['vector_query'])->toBe([0.1, 0.2, 0.3])
        ->and($driver->searches[1]['query']['q'])->toBe('*');
});

test('namespace filter is applied to every pass', function () {
    Settings::set('knowledge.synonym_weighting_enabled', true, 'boolean');

    [$provider, $driver] = weightedSemanticProvider(false, []);

    $source = KnowledgeSource::factory()->create(['namespace' => 'erp']);
    Document::factory()->create(['knowledge_source_id' => $source->id, 'status' => 'indexed']);

    $driver->hitsByQuery['car'] = [];
    $driver->hitsByQuery['car automobile vehicle'] = [];

    $provider->search(new SearchQuery(query: 'car', namespace: 'erp', maxResults: 10));

    expect($driver->searches[0]['query']['filter_by'])->toBe('namespace:=erp')
        ->and($driver->searches[1]['query']['filter_by'])->toBe('namespace:=erp');
});
