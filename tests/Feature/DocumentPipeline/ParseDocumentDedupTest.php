<?php

use App\DocumentPipeline\Parsers\TikaParser;
use App\Jobs\DocumentPipeline\ParseDocument;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Models\Document;
use Illuminate\Support\Facades\Bus;

test('marks document as duplicate when content hash already exists', function () {
    Bus::fake();

    // Create an existing parsed document with known content hash
    $existingContent = 'Hello World — this is unique content.';
    $existingHash = hash('sha256', $existingContent);

    Document::factory()->create([
        'content' => $existingContent,
        'content_hash' => $existingHash,
        'status' => DocumentStatus::Parsed->value,
        'parsed_at' => now(),
    ]);

    // Create a second document that will parse to the same content
    $duplicateDoc = Document::factory()->create([
        'content' => null,
        'content_hash' => null,
        'status' => DocumentStatus::Discovered->value,
    ]);

    // Mock the TikaParser to return the duplicate content
    $parser = mock(TikaParser::class);
    $parser->shouldReceive('parse')
        ->once()
        ->andReturn([
            'content' => $existingContent,
            'metadata' => ['Content-Type' => 'text/plain'],
        ]);

    $job = new ParseDocument($duplicateDoc->id);
    $job->handle($parser);

    $duplicateDoc->refresh();

    expect($duplicateDoc->status)->toBe(DocumentStatus::Duplicate->value)
        ->and($duplicateDoc->content_hash)->toBe($existingHash)
        ->and($duplicateDoc->error_message)->toContain('Duplicate content detected');
});

test('proceeds normally when content hash is unique', function () {
    Bus::fake();

    $doc = Document::factory()->create([
        'content' => null,
        'content_hash' => null,
        'status' => DocumentStatus::Discovered->value,
    ]);

    $uniqueContent = 'Brand new content never seen before.';
    $parser = mock(TikaParser::class);
    $parser->shouldReceive('parse')
        ->once()
        ->andReturn([
            'content' => $uniqueContent,
            'metadata' => ['Content-Type' => 'text/plain'],
        ]);

    $job = new ParseDocument($doc->id);
    $job->handle($parser);

    $doc->refresh();

    expect($doc->status)->toBe(DocumentStatus::Parsed->value)
        ->and($doc->content_hash)->toBe(hash('sha256', $uniqueContent))
        ->and($doc->content)->toBe($uniqueContent)
        ->and($doc->parsed_at)->not->toBeNull();
});

test('allows re-upload when previous duplicate is stale', function () {
    Bus::fake();

    $content = 'Important document content.';
    $hash = hash('sha256', $content);

    // Stale document with this content hash
    Document::factory()->create([
        'content' => $content,
        'content_hash' => $hash,
        'status' => 'stale',
        'parsed_at' => now(),
    ]);

    // New document with same content
    $doc = Document::factory()->create([
        'content' => null,
        'content_hash' => null,
        'status' => DocumentStatus::Discovered->value,
    ]);

    $parser = mock(TikaParser::class);
    $parser->shouldReceive('parse')
        ->once()
        ->andReturn([
            'content' => $content,
            'metadata' => ['Content-Type' => 'text/plain'],
        ]);

    $job = new ParseDocument($doc->id);
    $job->handle($parser);

    $doc->refresh();

    // Should proceed normally since the existing document is stale
    expect($doc->status)->toBe(DocumentStatus::Parsed->value);
});
