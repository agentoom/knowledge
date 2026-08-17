<?php

use App\DocumentPipeline\Chunking\FixedSizeChunking;
use App\DocumentPipeline\Chunking\MarkdownChunking;
use App\DocumentPipeline\Services\TokenAwareChunker;
use App\DocumentPipeline\Services\TokenCounter;
use App\Jobs\DocumentPipeline\ChunkDocument;
use App\Jobs\DocumentPipeline\EnrichChunk;
use App\Knowledge\Models\Chunk;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Settings\Facades\Settings;
use Illuminate\Support\Facades\Queue;

/**
 * Rebuild the first N tokens of a chunk as a |-joined string for overlap
 * assertions without reserializing the original text.
 */
function tokenPrefix(string $chunk, TokenCounter $counter, int $n): string
{
    $tokens = array_slice($counter->tokens($chunk), 0, $n);

    return implode('|', array_map(
        fn (array $t): string => substr($chunk, $t['start'], $t['end'] - $t['start']),
        $tokens
    ));
}

/**
 * Rebuild the last N tokens of a chunk as a |-joined string.
 */
function tokenSuffix(string $chunk, TokenCounter $counter, int $n): string
{
    $tokens = array_slice($counter->tokens($chunk), -$n);

    return implode('|', array_map(
        fn (array $t): string => substr($chunk, $t['start'], $t['end'] - $t['start']),
        $tokens
    ));
}

// --- TokenCounter ---

test('token counter counts words, punctuation, and numbers deterministically', function () {
    $counter = new TokenCounter;

    expect($counter->count('Hello, world!'))->toBe(4)
        ->and($counter->count('one two three'))->toBe(3)
        ->and($counter->count('1,000.50'))->toBe(5)
        ->and($counter->count("don't-stop"))->toBe(1);
});

test('token counter handles unicode without corrupting byte offsets', function () {
    $counter = new TokenCounter;

    $text = 'héllo wörld 日本語';

    expect($counter->count($text))->toBe(3);

    $tokens = $counter->tokens($text);

    // Byte offsets must slice the original text back to the exact tokens.
    foreach ($tokens as $token) {
        $sliced = substr($text, $token['start'], $token['end'] - $token['start']);

        expect($sliced)->not->toBe('');
    }

    expect(substr($text, $tokens[2]['start'], $tokens[2]['end'] - $tokens[2]['start']))->toBe('日本語');
});

test('token counter returns empty token list for empty input', function () {
    $counter = new TokenCounter;

    expect($counter->count(''))->toBe(0)
        ->and($counter->tokens(''))->toBe([]);
});

// --- TokenAwareChunker ---

test('every produced chunk stays at or below the configured cap', function () {
    $counter = new TokenCounter;
    $chunker = new TokenAwareChunker($counter);

    // ~60 tokens of prose so the fixed-size strategy over-produces.
    $content = implode(' ', array_map(fn (int $i): string => "t{$i}", range(0, 59)));

    $chunks = $chunker->chunk(new FixedSizeChunking(chunkSize: 1000), $content, maxTokens: 10, overlapTokens: 2);

    expect($chunks)->not->toBeEmpty();

    foreach ($chunks as $chunk) {
        expect($counter->count($chunk))->toBeLessThanOrEqual(10);
    }
});

test('adjacent oversized splits share the configured token overlap', function () {
    $counter = new TokenCounter;
    $chunker = new TokenAwareChunker($counter);

    $content = implode(' ', array_map(fn (int $i): string => "t{$i}", range(0, 59)));

    $chunks = $chunker->chunk(new FixedSizeChunking(chunkSize: 1000), $content, maxTokens: 10, overlapTokens: 4);

    expect(count($chunks))->toBeGreaterThan(1);

    for ($i = 1; $i < count($chunks); $i++) {
        expect(tokenSuffix($chunks[$i - 1], $counter, 4))->toBe(tokenPrefix($chunks[$i], $counter, 4));
    }
});

test('base strategy boundaries remain intact when already under the cap', function () {
    $chunker = new TokenAwareChunker(new TokenCounter);

    $content = "# Heading\n\n## Sub\n\nShort section one.\n\nShort section two.";

    $strategy = new MarkdownChunking;

    $expected = $strategy->chunk($content);

    $chunks = $chunker->chunk($strategy, $content, maxTokens: 500, overlapTokens: 10);

    expect($chunks)->toBe($expected);
});

test('empty input returns an empty array', function () {
    $chunker = new TokenAwareChunker(new TokenCounter);

    expect($chunker->chunk(new FixedSizeChunking, '', maxTokens: 10, overlapTokens: 2))->toBe([]);
});

test('invalid limits are rejected', function () {
    $chunker = new TokenAwareChunker(new TokenCounter);
    $strategy = new FixedSizeChunking;

    expect(fn () => $chunker->chunk($strategy, 'text', maxTokens: 0, overlapTokens: 0))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $chunker->chunk($strategy, 'text', maxTokens: 10, overlapTokens: -1))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $chunker->chunk($strategy, 'text', maxTokens: 10, overlapTokens: 10))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $chunker->chunk($strategy, 'text', maxTokens: 10, overlapTokens: 11))
        ->toThrow(InvalidArgumentException::class);
});

// --- ChunkDocument + EnrichChunk integration ---

test('chunk document persists token-bounded chunks with overlap', function () {
    Queue::fake();

    Settings::set('knowledge.chunk_max_tokens', 20, 'integer');
    Settings::set('knowledge.chunk_overlap_tokens', 4, 'integer');

    $source = KnowledgeSource::create([
        'name' => 'Token Source',
        'slug' => 'token-source',
        'provider_type' => 'filesystem',
        'namespace' => 'docs',
        'is_active' => true,
    ]);

    $document = Document::create([
        'knowledge_source_id' => $source->id,
        'path' => '/test/token-doc.md',
        'filename' => 'token-doc.md',
        'mime_type' => 'text/markdown',
        'content' => implode(' ', array_map(fn (int $i): string => "t{$i}", range(0, 119))),
        'status' => 'parsed',
    ]);

    (new ChunkDocument($document->id))->handle();

    $document->refresh();

    expect($document->status)->toBe('chunked')
        ->and($document->chunks()->count())->toBeGreaterThan(1);

    $counter = new TokenCounter;
    $chunks = $document->chunks()->orderBy('sequence')->get();

    foreach ($chunks as $chunk) {
        expect($chunk->token_count)->toBeLessThanOrEqual(20)
            ->and($chunk->token_count)->toBe($counter->count($chunk->content));
    }

    // Adjacent oversized splits share exactly four token positions.
    for ($i = 1; $i < $chunks->count(); $i++) {
        expect(tokenSuffix($chunks[$i - 1]->content, $counter, 4))
            ->toBe(tokenPrefix($chunks[$i]->content, $counter, 4));
    }
});

test('enrich chunk metadata token count matches the persisted count', function () {
    Queue::fake();

    Settings::set('knowledge.chunk_max_tokens', 20, 'integer');
    Settings::set('knowledge.chunk_overlap_tokens', 4, 'integer');

    $source = KnowledgeSource::create([
        'name' => 'Enrich Source',
        'slug' => 'enrich-source',
        'provider_type' => 'filesystem',
        'namespace' => 'docs',
        'is_active' => true,
    ]);

    $document = Document::create([
        'knowledge_source_id' => $source->id,
        'path' => '/test/enrich-doc.md',
        'filename' => 'enrich-doc.md',
        'mime_type' => 'text/markdown',
        'content' => implode(' ', array_map(fn (int $i): string => "t{$i}", range(0, 119))),
        'status' => 'parsed',
    ]);

    (new ChunkDocument($document->id))->handle();

    $chunk = $document->chunks()->orderBy('sequence')->first();

    (new EnrichChunk($chunk->id))->handle();

    $chunk->refresh();

    expect($chunk->metadata['token_count'])->toBe($chunk->token_count);
});
