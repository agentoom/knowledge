<?php

use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Knowledge\Services\MetadataRegistryService;
use App\Mcp\Resources\DocumentResource;
use App\Mcp\Resources\ListDocumentsResource;
use App\Mcp\Resources\ListSourcesResource;
use App\Mcp\Resources\SourceResource;
use App\Mcp\Servers\KnowledgeServer;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Laravel\Mcp\Server\Testing\TestResponse;

beforeEach(function () {
    // KnowledgeSource observers dispatch the document pipeline; keep tests
    // focused on the resources under test.
    Bus::fake();
});

/**
 * Build a source with an active provider and a couple of documents.
 *
 * The provider row is created by the KnowledgeSourceObserver on source
 * creation.
 */
function createSourceWithDocuments(array $sourceState = []): array
{
    $source = KnowledgeSource::factory()->create(array_merge([
        'provider_type' => 'filesystem',
        'namespace' => 'docs',
        'is_active' => true,
    ], $sourceState));

    $older = Document::factory()->create([
        'knowledge_source_id' => $source->id,
        'filename' => 'older.txt',
        'status' => 'indexed',
        'content' => 'Older parsed content.',
        'indexed_at' => now()->subDay(),
        'created_at' => now()->subDay(),
    ]);

    $newer = Document::factory()->create([
        'knowledge_source_id' => $source->id,
        'filename' => 'newer.txt',
        'status' => 'indexed',
        'content' => 'Newer parsed content.',
        'indexed_at' => now(),
        'created_at' => now(),
    ]);

    return ['source' => $source, 'older' => $older, 'newer' => $newer];
}

/**
 * Create an API key with the given scopes/namespaces and return the plain key.
 */
function createHttpApiKey(array $scopes, array $namespaces = []): string
{
    $plainKey = 'ak-'.bin2hex(random_bytes(16));

    ApiKey::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Resource Test Key',
        'key' => Hash::make($plainKey),
        'key_prefix' => substr($plainKey, 0, 8),
        'scopes' => $scopes,
        'knowledge_namespaces' => $namespaces,
        'expires_at' => null,
    ]);

    return $plainKey;
}

/**
 * Decode the JSON text payload of a resource harness response.
 *
 * @return array<string, mixed>
 */
function resourcePayload(TestResponse $response): array
{
    $property = new ReflectionProperty(TestResponse::class, 'response');
    $jsonRpcResponse = $property->getValue($response);

    $contents = $jsonRpcResponse->toArray()['result']['contents'] ?? [];

    return json_decode((string) ($contents[0]['text'] ?? '{}'), true) ?? [];
}

test('list sources resource returns active sources with provider metadata and namespaces', function () {
    ['source' => $source] = createSourceWithDocuments();

    app(MetadataRegistryService::class)->build();

    $response = KnowledgeServer::resource(ListSourcesResource::class);

    $response->assertOk()->assertSee($source->namespace);

    $payload = resourcePayload($response);

    expect($payload['total_sources'])->toBe(1)
        ->and($payload['sources'][0]['class'])->toBe('App\\Providers\\Filesystem\\FilesystemProvider')
        ->and($payload['namespaces'])->toContain('docs');
});

test('list sources resource filters inactive sources and providers', function () {
    ['source' => $inactiveSource] = createSourceWithDocuments(['is_active' => false, 'namespace' => 'hr']);

    $inactiveSource->providers()->update(['status' => 'inactive']);

    createSourceWithDocuments(['namespace' => 'docs']);

    app(MetadataRegistryService::class)->build();

    $response = KnowledgeServer::resource(ListSourcesResource::class);
    $payload = resourcePayload($response);

    expect($payload['total_sources'])->toBe(1)
        ->and($payload['namespaces'])->toBe(['docs']);
});

test('source resource returns source and provider metadata with a bounded document summary', function () {
    ['source' => $source, 'older' => $older] = createSourceWithDocuments();

    $response = KnowledgeServer::resource(SourceResource::class, [
        'source' => (string) $source->id,
    ]);

    $response->assertOk()
        ->assertSee($source->name)
        ->assertSee($older->filename);

    $payload = resourcePayload($response);

    expect($payload['source']['namespace'])->toBe('docs')
        ->and($payload['namespace'])->toBe('docs')
        ->and($payload['document_summary']['count'])->toBe(2)
        ->and($payload['provider']['class'])->toBe('App\\Providers\\Filesystem\\FilesystemProvider')
        ->and($payload['provider']['status'])->toBe('active');
});

test('source resource resolves by slug and namespace', function () {
    ['source' => $source] = createSourceWithDocuments();

    $bySlug = KnowledgeServer::resource(SourceResource::class, ['source' => $source->slug]);
    $byNamespace = KnowledgeServer::resource(SourceResource::class, ['source' => 'docs']);

    $bySlug->assertOk();
    $byNamespace->assertOk()->assertSee($source->name);
});

test('source resource rejects unknown and inactive sources', function () {
    KnowledgeServer::resource(SourceResource::class, ['source' => 'nonexistent'])->assertHasErrors();

    ['source' => $inactive] = createSourceWithDocuments(['is_active' => false]);
    KnowledgeServer::resource(SourceResource::class, ['source' => (string) $inactive->id])->assertHasErrors();
});

test('list documents resource returns only the source documents ordered newest first', function () {
    ['source' => $source, 'older' => $older, 'newer' => $newer] = createSourceWithDocuments();

    $otherSource = KnowledgeSource::factory()->create(['namespace' => 'erp', 'is_active' => true]);
    Document::factory()->create([
        'knowledge_source_id' => $otherSource->id,
        'filename' => 'other.txt',
        'status' => 'indexed',
        'content' => 'Other source content.',
    ]);

    $response = KnowledgeServer::resource(ListDocumentsResource::class, [
        'source' => $source->slug,
    ]);

    $response->assertOk()->assertSee($older->filename)->assertSee($newer->filename);

    $payload = resourcePayload($response);

    expect($payload['documents'])->toHaveCount(2)
        ->and($payload['documents'][0]['id'])->toBe($newer->id)
        ->and(collect($payload['documents'])->pluck('filename'))->not->toContain('other.txt');
});

test('list documents resource rejects unknown and inactive sources', function () {
    KnowledgeServer::resource(ListDocumentsResource::class, ['source' => 'nope'])->assertHasErrors();

    ['source' => $inactive] = createSourceWithDocuments(['is_active' => false]);
    KnowledgeServer::resource(ListDocumentsResource::class, ['source' => (string) $inactive->id])->assertHasErrors();
});

test('document resource returns metadata, parsed content and the source namespace', function () {
    ['source' => $source, 'newer' => $newer] = createSourceWithDocuments();

    $response = KnowledgeServer::resource(DocumentResource::class, [
        'id' => (string) $newer->id,
    ]);

    $response->assertOk()
        ->assertSee('newer.txt')
        ->assertSee('Newer parsed content.')
        ->assertSee('docs');

    $payload = resourcePayload($response);

    expect($payload['namespace'])->toBe('docs')
        ->and($payload['document']['status'])->toBe('indexed')
        ->and($payload['content'])->toBe('Newer parsed content.');
});

test('document resource rejects unknown, non-numeric and unavailable documents', function () {
    KnowledgeServer::resource(DocumentResource::class, ['id' => '999999'])->assertHasErrors();
    KnowledgeServer::resource(DocumentResource::class, ['id' => 'abc'])->assertHasErrors();

    $errorDoc = Document::factory()->create([
        'knowledge_source_id' => KnowledgeSource::factory()->create(['is_active' => true])->id,
        'filename' => 'broken.txt',
        'status' => 'error',
        'content' => null,
    ]);

    KnowledgeServer::resource(DocumentResource::class, ['id' => (string) $errorDoc->id])->assertHasErrors();
});

test('document resource rejects documents on inactive sources', function () {
    ['source' => $inactiveSource] = createSourceWithDocuments(['is_active' => false]);

    $doc = Document::factory()->create([
        'knowledge_source_id' => $inactiveSource->id,
        'filename' => 'hidden.txt',
        'status' => 'indexed',
        'content' => 'Hidden content.',
    ]);

    KnowledgeServer::resource(DocumentResource::class, ['id' => (string) $doc->id])->assertHasErrors();
});

test('local transport reads resources without api key attributes', function () {
    // No API-key request attribute exists for local/stdio transport — access is allowed.
    ['source' => $source, 'newer' => $newer] = createSourceWithDocuments();

    KnowledgeServer::resource(DocumentResource::class, ['id' => (string) $newer->id])->assertOk();
});

test('resource reads are denied when the presented api key no longer exists', function () {
    ['newer' => $newer] = createSourceWithDocuments();

    $request = app(Request::class);
    $request->attributes->set('mcp_api_key_id', 999999);

    try {
        $response = KnowledgeServer::resource(DocumentResource::class, ['id' => (string) $newer->id]);

        $response->assertHasErrors();
    } finally {
        $request->attributes->remove('mcp_api_key_id');
    }
});

test('web read resource requires the mcp:use scope', function () {
    ['newer' => $newer] = createSourceWithDocuments();

    $plainKey = createHttpApiKey(['billing:read']);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainKey)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/read',
            'params' => ['uri' => 'knowledge://documents/'.$newer->id],
            'id' => 1,
        ]);

    expect($response->status())->toBe(200)
        ->and($response->json('error.message'))->toContain('Not authorized');
});

test('web read resource rejects expired keys', function () {
    ['newer' => $newer] = createSourceWithDocuments();

    $plainKey = 'ak-'.bin2hex(random_bytes(16));

    ApiKey::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'Expired Key',
        'key' => Hash::make($plainKey),
        'key_prefix' => substr($plainKey, 0, 8),
        'scopes' => ['mcp:use'],
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainKey)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/read',
            'params' => ['uri' => 'knowledge://documents/'.$newer->id],
            'id' => 1,
        ]);

    expect($response->status())->toBe(401);
});

test('web read resource enforces knowledge namespace allowlists', function () {
    ['newer' => $docsDoc] = createSourceWithDocuments();

    $erpSource = KnowledgeSource::factory()->create(['namespace' => 'erp', 'slug' => 'erp', 'is_active' => true]);
    $erpDoc = Document::factory()->create([
        'knowledge_source_id' => $erpSource->id,
        'filename' => 'erp.txt',
        'status' => 'indexed',
        'content' => 'ERP content.',
    ]);

    $plainKey = createHttpApiKey(['mcp:use'], ['docs']);

    // Allowed namespace: read succeeds with parsed content.
    $allowed = $this->withHeader('Authorization', 'Bearer '.$plainKey)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/read',
            'params' => ['uri' => 'knowledge://documents/'.$docsDoc->id],
            'id' => 1,
        ]);

    expect($allowed->status())->toBe(200)
        ->and($allowed->json('result.contents.0.text'))->toContain('Newer parsed content.');

    // Disallowed namespace: MCP error.
    $denied = $this->withHeader('Authorization', 'Bearer '.$plainKey)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/read',
            'params' => ['uri' => 'knowledge://documents/'.$erpDoc->id],
            'id' => 1,
        ]);

    expect($denied->status())->toBe(200)
        ->and($denied->json('error.message'))->toContain('Not authorized');
});

test('empty namespace allowlist is unrestricted', function () {
    ['newer' => $docsDoc] = createSourceWithDocuments();

    $plainKey = createHttpApiKey(['admin:*'], []);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainKey)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/read',
            'params' => ['uri' => 'knowledge://documents/'.$docsDoc->id],
            'id' => 1,
        ]);

    expect($response->status())->toBe(200)
        ->and($response->json('result.contents.0.text'))->toContain('Newer parsed content.');
});

test('web resources list exposes the static source resource', function () {
    $plainKey = createHttpApiKey(['mcp:use']);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainKey)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/list',
            'params' => [],
            'id' => 1,
        ]);

    expect($response->status())->toBe(200);

    $uris = collect($response->json('result.resources'))->pluck('uri')->all();

    expect($uris)->toContain('knowledge://sources');
});

test('web resource templates list exposes the browsing templates', function () {
    $plainKey = createHttpApiKey(['mcp:use']);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainKey)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/templates/list',
            'params' => [],
            'id' => 1,
        ]);

    expect($response->status())->toBe(200);

    $templates = collect($response->json('result.resourceTemplates'))->pluck('uriTemplate')->all();

    expect($templates)->toContain('knowledge://sources/{source}')
        ->toContain('knowledge://sources/{source}/documents')
        ->toContain('knowledge://documents/{id}');
});
