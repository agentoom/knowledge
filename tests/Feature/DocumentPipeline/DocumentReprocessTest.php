<?php

use App\DocumentPipeline\Parsers\TikaParser;
use App\DocumentPipeline\Services\PipelineOrchestrator;
use App\Enums\Role;
use App\Jobs\DocumentPipeline\DeindexDocument;
use App\Jobs\DocumentPipeline\ParseDocument;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Livewire\Admin\Documents\Show;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

beforeEach(function () {
    Bus::fake();
});

// --- ParseDocument retry contract ---

test('parse document exposes retry metadata', function () {
    $document = Document::factory()->create(['status' => 'discovered']);

    $job = new ParseDocument($document->id);

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([30, 120]);
});

test('parse document rethrows parser exceptions and persists the error state', function () {
    $document = Document::factory()->create(['status' => 'discovered']);

    $parser = mock(TikaParser::class);
    $parser->shouldReceive('parse')
        ->once()
        ->andThrow(new RuntimeException('Tika unavailable'));

    $job = new ParseDocument($document->id);

    expect(fn () => $job->handle($parser))->toThrow(RuntimeException::class, 'Tika unavailable');

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Error->value)
        ->and($document->error_message)->toBe('Tika unavailable');
});

test('a failing parse recovers on a later attempt', function () {
    $document = Document::factory()->create(['status' => 'discovered']);

    $parser = mock(TikaParser::class);
    $parser->shouldReceive('parse')
        ->ordered()
        ->twice()
        ->andThrow(new RuntimeException('Tika timeout'));

    $parser->shouldReceive('parse')
        ->ordered()
        ->once()
        ->andReturn([
            'content' => 'Recovered content after retry.',
            'metadata' => ['Content-Type' => 'text/plain'],
        ]);

    $job = new ParseDocument($document->id);

    foreach ([1, 2] as $attempt) {
        try {
            $job->handle($parser);
        } catch (RuntimeException) {
            // expected while the parser keeps failing
        }
    }

    $job->handle($parser);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Parsed->value)
        ->and($document->content)->toBe('Recovered content after retry.')
        ->and($document->error_message)->toBeNull();
});

// --- PipelineOrchestrator::reprocess ---

test('reprocess resets an error document and dispatches deindex plus parse', function () {
    $source = KnowledgeSource::factory()->create(['provider_type' => 'filesystem', 'is_active' => true]);

    $document = Document::factory()->create([
        'knowledge_source_id' => $source->id,
        'status' => 'error',
        'error_message' => 'boom',
        'parsed_at' => now()->subHour(),
        'chunked_at' => now()->subHour(),
        'indexed_at' => now()->subHour(),
    ]);

    $chunkIds = [
        $document->chunks()->create(['sequence' => 0, 'content' => 'old chunk', 'token_count' => 2])->id,
        $document->chunks()->create(['sequence' => 1, 'content' => 'old chunk 2', 'token_count' => 2])->id,
    ];

    app(PipelineOrchestrator::class)->reprocess($document);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Discovered->value)
        ->and($document->error_message)->toBeNull()
        ->and($document->parsed_at)->toBeNull()
        ->and($document->chunked_at)->toBeNull()
        ->and($document->indexed_at)->toBeNull()
        ->and($document->chunks()->count())->toBe(0);

    Bus::assertDispatched(DeindexDocument::class, fn (DeindexDocument $job) => $job->chunkIds === $chunkIds);
    Bus::assertDispatched(ParseDocument::class, fn (ParseDocument $job) => $job->documentId === $document->id);
});

test('reprocess is a safe no-op for non-error documents', function () {
    $source = KnowledgeSource::factory()->create(['provider_type' => 'filesystem', 'is_active' => true]);

    $document = Document::factory()->create([
        'knowledge_source_id' => $source->id,
        'status' => 'parsed',
    ]);

    app(PipelineOrchestrator::class)->reprocess($document);

    $document->refresh();

    expect($document->status)->toBe('parsed');

    Bus::assertNotDispatched(ParseDocument::class);
    Bus::assertNotDispatched(DeindexDocument::class);
});

test('reprocess is a safe no-op for web-backed error documents', function () {
    $source = KnowledgeSource::factory()->create(['provider_type' => 'web', 'is_active' => true]);

    $document = Document::factory()->create([
        'knowledge_source_id' => $source->id,
        'status' => 'error',
        'error_message' => 'fetch failed',
    ]);

    app(PipelineOrchestrator::class)->reprocess($document);

    $document->refresh();

    expect($document->status)->toBe('error');

    Bus::assertNotDispatched(ParseDocument::class);
    Bus::assertNotDispatched(DeindexDocument::class);
});

// --- knowledge:documents:reprocess command ---

test('command reprocesses error documents and reports counts', function () {
    $filesystem = KnowledgeSource::factory()->create(['provider_type' => 'filesystem', 'is_active' => true]);
    $web = KnowledgeSource::factory()->create(['provider_type' => 'web', 'is_active' => true]);

    $errorOne = Document::factory()->create(['knowledge_source_id' => $filesystem->id, 'status' => 'error']);
    $errorTwo = Document::factory()->create(['knowledge_source_id' => $filesystem->id, 'status' => 'error']);
    Document::factory()->create(['knowledge_source_id' => $web->id, 'status' => 'error']);
    Document::factory()->create(['knowledge_source_id' => $filesystem->id, 'status' => 'parsed']);

    $exitCode = Artisan::call('knowledge:documents:reprocess');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Queued 2 document(s)');

    Bus::assertDispatchedTimes(ParseDocument::class, 2);
    Bus::assertDispatched(ParseDocument::class, fn (ParseDocument $job) => in_array($job->documentId, [$errorOne->id, $errorTwo->id], true));
});

test('command supports source scoping and limit', function () {
    $source = KnowledgeSource::factory()->create(['provider_type' => 'filesystem', 'is_active' => true]);
    $other = KnowledgeSource::factory()->create(['provider_type' => 'filesystem', 'is_active' => true]);

    $docs = Document::factory()->count(3)->create(['knowledge_source_id' => $source->id, 'status' => 'error']);
    Document::factory()->count(2)->create(['knowledge_source_id' => $other->id, 'status' => 'error']);

    Artisan::call('knowledge:documents:reprocess', [
        '--source' => $source->slug,
        '--limit' => 2,
    ]);

    Bus::assertDispatchedTimes(ParseDocument::class, 2);
    Bus::assertDispatched(ParseDocument::class, fn (ParseDocument $job) => in_array($job->documentId, $docs->pluck('id')->all(), true));
});

test('command is a successful no-op when no error documents exist', function () {
    Document::factory()->create(['status' => 'parsed']);

    $exitCode = Artisan::call('knowledge:documents:reprocess');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('No error documents found');

    Bus::assertNotDispatched(ParseDocument::class);
});

// --- Livewire error-button behavior ---

test('document show offers reprocess for error documents of non-web sources', function () {
    $admin = User::factory()->create(['role' => Role::Admin->value]);

    $source = KnowledgeSource::factory()->create(['provider_type' => 'filesystem', 'is_active' => true]);
    $document = Document::factory()->create([
        'knowledge_source_id' => $source->id,
        'status' => 'error',
        'error_message' => 'parse failed',
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['document' => $document->id])
        ->assertSee('Reprocess')
        ->call('reprocess')
        ->assertHasNoErrors()
        ->assertSet('status', 'discovered');
});

test('document show hides reprocess for web-backed error documents', function () {
    $admin = User::factory()->create(['role' => Role::Admin->value]);

    $source = KnowledgeSource::factory()->create(['provider_type' => 'web', 'is_active' => true]);
    $document = Document::factory()->create([
        'knowledge_source_id' => $source->id,
        'status' => 'error',
        'error_message' => 'crawl failed',
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['document' => $document->id])
        ->assertDontSee('Reprocess');
});
