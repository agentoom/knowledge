<?php

use App\DocumentPipeline\Services\PipelineOrchestrator;
use App\Jobs\DocumentPipeline\ChunkDocument;
use App\Jobs\DocumentPipeline\DiscoverDocuments;
use App\Jobs\DocumentPipeline\NormalizeDocument;
use App\Jobs\DocumentPipeline\ParseDocument;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('normalize document cleans whitespace', function () {
    $source = KnowledgeSource::create([
        'name' => 'Test Source',
        'slug' => 'test-source',
        'provider_type' => 'filesystem',
        'namespace' => 'test',
        'is_active' => true,
    ]);

    $document = Document::create([
        'knowledge_source_id' => $source->id,
        'path' => '/test/doc.txt',
        'filename' => 'doc.txt',
        'mime_type' => 'text/plain',
        'content' => "Hello   World\r\n\r\n\r\nExtra    spaces.",
        'status' => 'parsed',
    ]);

    $job = new NormalizeDocument($document->id);
    $job->handle();

    $document->refresh();

    expect($document->content)->toBe("Hello World\n\nExtra spaces.");
});

test('chunk document creates chunks from content', function () {
    $source = KnowledgeSource::create([
        'name' => 'Test Source',
        'slug' => 'test-source',
        'provider_type' => 'filesystem',
        'namespace' => 'test',
        'is_active' => true,
    ]);

    $document = Document::create([
        'knowledge_source_id' => $source->id,
        'path' => '/test/doc.md',
        'filename' => 'doc.md',
        'mime_type' => 'text/markdown',
        'content' => 'Short content for testing chunking.',
        'status' => 'normalized',
    ]);

    $job = new ChunkDocument($document->id);
    $job->handle();

    $document->refresh();

    expect($document->status)->toBe('chunked')
        ->and($document->chunked_at)->not->toBeNull();

    $chunks = $document->chunks;

    expect($chunks)->toHaveCount(1)
        ->and($chunks->first()->sequence)->toBe(0)
        ->and($chunks->first()->content)->toContain('Short content');
});

test('chunk document handles empty content', function () {
    $source = KnowledgeSource::create([
        'name' => 'Test Source',
        'slug' => 'test-source',
        'provider_type' => 'filesystem',
        'namespace' => 'test',
        'is_active' => true,
    ]);

    $document = Document::create([
        'knowledge_source_id' => $source->id,
        'path' => '/test/empty.txt',
        'filename' => 'empty.txt',
        'content' => '',
        'status' => 'normalized',
    ]);

    $job = new ChunkDocument($document->id);
    $job->handle();

    $document->refresh();

    expect($document->status)->toBe('error')
        ->and($document->error_message)->toContain('No content');
});

test('parse document updates content and status', function () {
    Http::fake([
        '*' => Http::response("Parsed content here.\n\nWith metadata.", 200, [
            'Content-Type' => 'text/plain',
        ]),
    ]);

    $source = KnowledgeSource::create([
        'name' => 'Test Source',
        'slug' => 'test-source',
        'provider_type' => 'filesystem',
        'namespace' => 'test',
        'is_active' => true,
    ]);

    $testPath = storage_path('app/test-doc.txt');
    file_put_contents($testPath, 'Sample content');

    $document = Document::create([
        'knowledge_source_id' => $source->id,
        'path' => $testPath,
        'filename' => 'test-doc.txt',
        'status' => 'discovered',
    ]);

    try {
        $job = new ParseDocument($document->id);
        app()->call([$job, 'handle']);

        $document->refresh();

        expect($document->status)->toBe('parsed')
            ->and($document->parsed_at)->not->toBeNull();
    } finally {
        @unlink($testPath);
    }
});

test('parse document handles errors gracefully', function () {
    Http::fake([
        '*' => Http::response('Server Error', 500),
    ]);

    $source = KnowledgeSource::create([
        'name' => 'Test Source',
        'slug' => 'test-source',
        'provider_type' => 'filesystem',
        'namespace' => 'test',
        'is_active' => true,
    ]);

    $testPath = storage_path('app/test-error.txt');
    file_put_contents($testPath, 'Sample');

    $document = Document::create([
        'knowledge_source_id' => $source->id,
        'path' => $testPath,
        'filename' => 'test-error.txt',
        'status' => 'discovered',
    ]);

    try {
        $job = new ParseDocument($document->id);
        app()->call([$job, 'handle']);

        $document->refresh();

        expect($document->status)->toBe('error')
            ->and($document->error_message)->not->toBeNull();
    } finally {
        @unlink($testPath);
    }
});

test('pipeline orchestrator dispatches discover batch', function () {
    Bus::fake();

    $source = KnowledgeSource::create([
        'name' => 'Test Source',
        'slug' => 'test-source',
        'provider_type' => 'filesystem',
        'namespace' => 'test',
        'is_active' => true,
    ]);

    $orchestrator = app(PipelineOrchestrator::class);
    $orchestrator->run($source);

    Bus::assertBatched(function ($batch) {
        return $batch->jobs->count() === 1
            && $batch->jobs->first() instanceof DiscoverDocuments;
    });
});
