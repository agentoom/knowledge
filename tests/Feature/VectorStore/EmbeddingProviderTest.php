<?php

use App\Contracts\EmbeddingProvider;
use App\Embedding\Providers\CohereEmbeddingProvider;
use App\Embedding\Providers\HuggingFaceEmbeddingProvider;
use App\Embedding\Providers\OpenAiEmbeddingProvider;
use App\Embedding\Providers\TypesenseProvider;
use App\Embedding\Services\EmbeddingManager;
use App\Jobs\DocumentPipeline\IndexChunk;
use App\Knowledge\Models\Chunk;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Providers\VectorStore\SemanticProvider;
use App\Retrieval\Models\SearchQuery;
use App\Settings\Facades\Settings;
use App\VectorStore\Models\VectorStore;
use App\VectorStore\Services\VectorStoreManager;
use Illuminate\Support\Facades\Http;

function activeTypesenseStore(): void
{
    VectorStore::create([
        'driver' => 'typesense',
        'config' => ['host' => 'http://typesense:8108', 'api_key' => 'xyz'],
        'is_active' => true,
    ]);
}

function typesenseFakes(): void
{
    Http::fake([
        'http://typesense:8108/collections/knowledge_chunks' => Http::response(['message' => 'not found'], 404),
        'http://typesense:8108/collections' => Http::response(['id' => 'knowledge_chunks'], 200),
        'http://typesense:8108/collections/knowledge_chunks/documents' => Http::response(['id' => '1'], 200),
    ]);
}

function queryParamsOf(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

    return $params;
}

beforeEach(function () {
    // Reset the per-process collection-dimension cache so dimension-conflict
    // behaviour is exercised in isolation from earlier tests.
    $property = new ReflectionProperty(IndexChunk::class, 'verifiedCollectionDimensions');
    $property->setValue(null, []);

    // Reset provider secrets that tests set via config()->set().
    config()->set('services.openai.api_key', null);
    config()->set('services.cohere.api_key', null);
    config()->set('services.huggingface.api_token', null);
});

test('openai provider sends expected payload with bearer auth and parses the vector', function () {
    Http::fake([
        'https://api.openai.com/*' => Http::response(['data' => [['embedding' => [0.1, 0.2, 0.3]]]], 200),
    ]);

    $provider = new OpenAiEmbeddingProvider([
        'api_key' => 'test-key',
        'base_url' => 'https://api.openai.com/v1',
        'model' => 'text-embedding-3-small',
    ]);

    $vector = $provider->embed('hello world', 'search_document');

    expect($vector)->toBe([0.1, 0.2, 0.3])
        ->and($provider->dimensions())->toBe(1536);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/embeddings'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['input'] === 'hello world'
            && $request['model'] === 'text-embedding-3-small';
    });
});

test('openai provider throws when api key is missing', function () {
    Http::fake();

    $provider = new OpenAiEmbeddingProvider(['base_url' => 'https://api.openai.com/v1']);

    expect(fn () => $provider->embed('hello'))->toThrow(RuntimeException::class, 'OPENAI_API_KEY');
});

test('cohere provider sends expected payload with bearer auth and parses float embeddings', function () {
    Http::fake([
        'https://api.cohere.com/*' => Http::response(['embeddings' => ['float' => [[0.5, 0.6]]]], 200),
    ]);

    $provider = new CohereEmbeddingProvider([
        'api_key' => 'test-key',
        'base_url' => 'https://api.cohere.com/v1',
        'model' => 'embed-english-v3.0',
    ]);

    $vector = $provider->embed('hello world', 'search_query');

    expect($vector)->toBe([0.5, 0.6])
        ->and($provider->dimensions())->toBe(1024);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.cohere.com/v1/embed'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['texts'] === ['hello world']
            && $request['model'] === 'embed-english-v3.0'
            && $request['input_type'] === 'search_query'
            && $request['embedding_types'] === ['float'];
    });
});

test('huggingface provider sends inputs and model and accepts a flat vector response', function () {
    Http::fake([
        'http://tei:8080/*' => Http::response([0.1, 0.2, 0.3], 200),
    ]);

    $provider = new HuggingFaceEmbeddingProvider([
        'endpoint' => 'http://tei:8080/embed',
        'model' => 'all-MiniLM-L6-v2',
        'dimensions' => 3,
    ]);

    $vector = $provider->embed('hello world');

    expect($vector)->toBe([0.1, 0.2, 0.3]);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://tei:8080/embed'
            && $request->method() === 'POST'
            && $request['inputs'] === 'hello world'
            && $request['model'] === 'all-MiniLM-L6-v2';
    });
});

test('huggingface provider normalizes nested, openai-style, and embeddings-style responses', function (array $body) {
    Http::fake(['http://tei:8080/*' => Http::response($body, 200)]);

    $provider = new HuggingFaceEmbeddingProvider([
        'endpoint' => 'http://tei:8080/embed',
        'dimensions' => 3,
    ]);

    expect($provider->embed('hello'))->toBe([0.1, 0.2, 0.3]);
})->with([
    'nested array' => [[[0.1, 0.2, 0.3]]],
    'openai style' => [['data' => [['embedding' => [0.1, 0.2, 0.3]]]]],
    'embeddings style' => [['embeddings' => [[0.1, 0.2, 0.3]]]],
]);

test('huggingface provider rejects dimension mismatched responses', function () {
    Http::fake(['http://tei:8080/*' => Http::response([0.1, 0.2], 200)]);

    $provider = new HuggingFaceEmbeddingProvider([
        'endpoint' => 'http://tei:8080/embed',
        'dimensions' => 3,
    ]);

    expect(fn () => $provider->embed('hello'))
        ->toThrow(RuntimeException::class, 'dimension mismatch: expected 3, got 2');
});

test('huggingface provider rejects malformed responses without vector data', function () {
    Http::fake(['http://tei:8080/*' => Http::response(['error' => 'boom'], 200)]);

    $provider = new HuggingFaceEmbeddingProvider([
        'endpoint' => 'http://tei:8080/embed',
        'dimensions' => 3,
    ]);

    expect(fn () => $provider->embed('hello'))
        ->toThrow(RuntimeException::class, 'missing the vector data');
});

test('huggingface provider rejects non numeric vector components', function () {
    Http::fake(['http://tei:8080/*' => Http::response(['data' => [['embedding' => ['a', 'b']]]], 200)]);

    $provider = new HuggingFaceEmbeddingProvider([
        'endpoint' => 'http://tei:8080/embed',
        'dimensions' => 2,
    ]);

    expect(fn () => $provider->embed('hello'))
        ->toThrow(RuntimeException::class, 'non-numeric');
});

test('providers surface HTTP failures with descriptive errors', function () {
    Http::fake([
        'https://api.openai.com/*' => Http::response(['error' => 'invalid api key'], 401),
    ]);

    $provider = new OpenAiEmbeddingProvider(['api_key' => 'bad', 'base_url' => 'https://api.openai.com/v1']);

    expect(fn () => $provider->embed('hello'))
        ->toThrow(RuntimeException::class, 'HTTP 401');
});

test('typesense provider represents managed embeddings and refuses external embedding', function () {
    $provider = new TypesenseProvider;

    expect($provider)->toBeInstanceOf(EmbeddingProvider::class)
        ->and(fn () => $provider->dimensions())->toThrow(LogicException::class)
        ->and(fn () => $provider->embed('hello'))->toThrow(LogicException::class);
});

test('manager resolves the active provider from settings and applies non-secret overrides', function () {
    config()->set('services.openai.api_key', 'env-key');
    Settings::set('knowledge.embedding_provider', 'openai', 'string');
    Settings::set('knowledge.embedding_model', 'custom-model', 'string');

    Http::fake(['https://api.openai.com/*' => Http::response(['data' => [['embedding' => [0.1]]]], 200)]);

    $provider = app(EmbeddingManager::class)->provider();

    expect($provider)->toBeInstanceOf(OpenAiEmbeddingProvider::class)
        ->and(app(EmbeddingManager::class)->activeProvider())->toBe('openai')
        ->and(app(EmbeddingManager::class)->isManaged())->toBeFalse();

    $provider->embed('hello', 'search_query');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/embeddings'
            && $request['model'] === 'custom-model'
            && $request->hasHeader('Authorization', 'Bearer env-key');
    });
});

test('manager defaults to typesense managed mode when no setting exists', function () {
    expect(app(EmbeddingManager::class)->activeProvider())->toBe('typesense')
        ->and(app(EmbeddingManager::class)->isManaged())->toBeTrue();
});

test('manager falls back to the huggingface model env key when settings are empty', function () {
    Settings::set('knowledge.embedding_model', '', 'string');
    Settings::set('knowledge.embedding_dimensions', 3, 'integer');
    config()->set('services.huggingface.model', 'env-hf-model');

    Http::fake(['http://tei:8080/*' => Http::response([0.1, 0.2, 0.3], 200)]);

    $provider = app(EmbeddingManager::class)->provider('huggingface');
    $provider->embed('hello');

    Http::assertSent(function ($request) {
        return $request->url() === 'http://tei:8080/embed'
            && $request['model'] === 'env-hf-model';
    });
});

test('index chunk in managed mode uses the embed schema and makes no external calls', function () {
    $source = KnowledgeSource::factory()->create(['is_active' => true]);
    $document = Document::factory()->create(['knowledge_source_id' => $source->id, 'status' => 'chunked']);
    $chunk = Chunk::factory()->create([
        'document_id' => $document->id,
        'content' => 'managed content',
        'metadata' => ['source_namespace' => 'docs'],
    ]);

    activeTypesenseStore();
    typesenseFakes();

    (new IndexChunk($chunk->id))->handle(app(VectorStoreManager::class));

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.openai.com'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.cohere.com'));

    Http::assertSent(function ($request) {
        return $request->url() === 'http://typesense:8108/collections'
            && $request->method() === 'POST'
            && ($request['fields'][7]['embed']['model_config']['model_name'] ?? null) === 'ts/all-MiniLM-L12-v2';
    });

    $chunk->refresh();
    $document->refresh();

    expect($chunk->indexed_at)->not->toBeNull()
        ->and($document->status)->toBe('indexed');
});

test('index chunk in external mode computes the embedding and passes it to the vector store', function () {
    config()->set('services.openai.api_key', 'env-key');
    Settings::set('knowledge.embedding_provider', 'openai', 'string');

    $source = KnowledgeSource::factory()->create(['is_active' => true]);
    $document = Document::factory()->create(['knowledge_source_id' => $source->id, 'status' => 'chunked']);
    $chunk = Chunk::factory()->create([
        'document_id' => $document->id,
        'content' => 'external content',
        'metadata' => ['source_namespace' => 'docs'],
    ]);

    activeTypesenseStore();

    Http::fake([
        'http://typesense:8108/collections/knowledge_chunks' => Http::sequence()
            ->push(['message' => 'not found'], 404)
            ->push(['message' => 'not found'], 404),
        'http://typesense:8108/collections' => Http::response(['id' => 'knowledge_chunks'], 200),
        'http://typesense:8108/collections/knowledge_chunks/documents' => Http::response(['id' => '1'], 200),
        'https://api.openai.com/*' => Http::response(['data' => [['embedding' => array_fill(0, 1536, 0.5)]]], 200),
    ]);

    (new IndexChunk($chunk->id))->handle(app(VectorStoreManager::class));

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.openai.com/v1/embeddings'
            && $request['input'] === 'external content';
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'http://typesense:8108/collections'
            && ($request['fields'][7]['num_dim'] ?? null) === 1536;
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'http://typesense:8108/collections/knowledge_chunks/documents'
            && $request->method() === 'POST'
            && $request['embedding'] !== null;
    });

    $chunk->refresh();
    $document->refresh();

    expect($chunk->indexed_at)->not->toBeNull()
        ->and($document->status)->toBe('indexed');
});

test('index chunk fails without indexing when existing collection dimension conflicts', function () {
    config()->set('services.openai.api_key', 'env-key');
    Settings::set('knowledge.embedding_provider', 'openai', 'string');

    $source = KnowledgeSource::factory()->create(['is_active' => true]);
    $document = Document::factory()->create(['knowledge_source_id' => $source->id, 'status' => 'chunked']);
    $chunk = Chunk::factory()->create([
        'document_id' => $document->id,
        'content' => 'conflicting content',
        'metadata' => ['source_namespace' => 'docs'],
    ]);

    activeTypesenseStore();

    Http::fake([
        'http://typesense:8108/collections/knowledge_chunks' => Http::response([
            'name' => 'knowledge_chunks',
            'fields' => [['name' => 'embedding', 'type' => 'float[]', 'num_dim' => 384]],
        ], 200),
        'https://api.openai.com/*' => Http::response(['data' => [['embedding' => [0.1]]]], 200),
    ]);

    (new IndexChunk($chunk->id))->handle(app(VectorStoreManager::class));

    Http::assertNotSent(fn ($request) => $request->url() === 'http://typesense:8108/collections/knowledge_chunks/documents');
    Http::assertNotSent(fn ($request) => $request->url() === 'https://api.openai.com/v1/embeddings');

    $chunk->refresh();

    expect($chunk->indexed_at)->toBeNull();
});

test('semantic provider managed hybrid keeps the embedded query string and makes no external calls', function () {
    activeTypesenseStore();

    Http::fake([
        'http://typesense:8108/collections/knowledge_chunks/documents/search*' => Http::response(['hits' => []], 200),
    ]);

    $provider = new SemanticProvider(app(VectorStoreManager::class));
    $result = $provider->search(new SearchQuery(query: 'quarterly revenue', searchType: 'hybrid'));

    expect($result->items)->toBe([]);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.openai.com'));

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/documents/search')) {
            return false;
        }

        $params = queryParamsOf($request->url());

        return $params['q'] === '*'
            && str_starts_with($params['vector_query'], 'embedding:([], alpha: 0.5, query: "quarterly revenue")');
    });
});

test('semantic provider external hybrid search translates the raw query vector into vector_query syntax', function () {
    config()->set('services.openai.api_key', 'env-key');
    Settings::set('knowledge.embedding_provider', 'openai', 'string');

    activeTypesenseStore();

    Http::fake([
        'https://api.openai.com/*' => Http::response(['data' => [['embedding' => [0.1, 0.2]]]], 200),
        'http://typesense:8108/collections/knowledge_chunks/documents/search*' => Http::response(['hits' => []], 200),
    ]);

    $provider = new SemanticProvider(app(VectorStoreManager::class));
    $result = $provider->search(new SearchQuery(query: 'quarterly revenue', searchType: 'hybrid'));

    expect($result->items)->toBe([]);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/documents/search')) {
            return false;
        }

        $params = queryParamsOf($request->url());

        return $params['q'] === 'quarterly revenue'
            && $params['vector_query'] === 'embedding:([0.1,0.2], alpha: 0.5, k: 25)';
    });
});

test('semantic provider external semantic search uses match-all plus a raw vector', function () {
    config()->set('services.openai.api_key', 'env-key');
    Settings::set('knowledge.embedding_provider', 'openai', 'string');

    activeTypesenseStore();

    Http::fake([
        'https://api.openai.com/*' => Http::response(['data' => [['embedding' => [0.1, 0.2]]]], 200),
        'http://typesense:8108/collections/knowledge_chunks/documents/search*' => Http::response(['hits' => []], 200),
    ]);

    $provider = new SemanticProvider(app(VectorStoreManager::class));
    $result = $provider->search(new SearchQuery(query: 'quarterly revenue', searchType: 'semantic'));

    expect($result->items)->toBe([]);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/documents/search')) {
            return false;
        }

        $params = queryParamsOf($request->url());

        return $params['q'] === '*'
            && $params['vector_query'] === 'embedding:([0.1,0.2], k: 25)';
    });
});

test('semantic provider keyword search stays provider agnostic without a vector', function () {
    config()->set('services.openai.api_key', 'env-key');
    Settings::set('knowledge.embedding_provider', 'openai', 'string');

    activeTypesenseStore();

    Http::fake([
        'http://typesense:8108/collections/knowledge_chunks/documents/search*' => Http::response(['hits' => []], 200),
    ]);

    $provider = new SemanticProvider(app(VectorStoreManager::class));
    $result = $provider->search(new SearchQuery(query: 'plain keyword query'));

    expect($result->items)->toBe([]);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.openai.com'));

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/documents/search')) {
            return false;
        }

        $params = queryParamsOf($request->url());

        return $params['q'] === 'plain keyword query'
            && ! isset($params['vector_query']);
    });
});
