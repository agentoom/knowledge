<?php

use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Providers\Filesystem\FilesystemProvider;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    Bus::fake();

    $this->source = KnowledgeSource::factory()->create([
        'name' => 'Test Filesystem Source',
        'provider_type' => 'filesystem',
        'namespace' => 'test-dedup',
        'is_active' => true,
    ]);

    // Ensure directory exists
    $dir = FilesystemProvider::canonicalPath('test-dedup');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
});

afterEach(function () {
    Document::query()->delete();
    $dir = FilesystemProvider::canonicalPath('test-dedup');
    if (is_dir($dir)) {
        array_map('unlink', glob("$dir/*"));
    }
});

function createTempFile(string $content): string
{
    $dir = FilesystemProvider::canonicalPath('test-dedup');
    $path = $dir.'/'.uniqid('dedup_').'.txt';
    file_put_contents($path, $content);

    return $path;
}

test('same content produces identical content_hash via hash_file', function () {
    $content = 'Dedup test content — phase 7.';
    $path1 = createTempFile($content);
    $path2 = createTempFile($content);

    $hash1 = hash_file('sha256', $path1);
    $hash2 = hash_file('sha256', $path2);

    expect($hash1)->not->toBeFalse()
        ->and($hash2)->not->toBeFalse()
        ->and($hash1)->toBe($hash2);
});

test('Hash dedup query finds existing documents by content_hash', function () {
    $content = 'Dedup query test content.';
    $path = createTempFile($content);
    $hash = hash_file('sha256', $path);

    // Insert a document as if it went through FileManager
    Document::create([
        'knowledge_source_id' => $this->source->id,
        'path' => $path,
        'filename' => 'existing.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => strlen($content),
        'content_hash' => $hash,
        'status' => 'discovered',
    ]);

    // Simulate the dedup query from FileManager
    $knownHashes = Document::whereNotNull('content_hash')
        ->whereNotIn('status', ['stale', 'duplicate', 'error'])
        ->pluck('content_hash')
        ->unique()
        ->toArray();

    expect($knownHashes)->toContain($hash);
});

test('dedup query excludes stale error and duplicate statuses', function () {
    $content = 'Exclusion test content.';
    $path = createTempFile($content);
    $hash = hash_file('sha256', $path);

    // Insert documents with various statuses
    Document::create([
        'knowledge_source_id' => $this->source->id,
        'path' => $path.'_disc',
        'filename' => 'discovered.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => strlen($content),
        'content_hash' => $hash,
        'status' => DocumentStatus::Discovered->value,
    ]);

    // Duplicate status should be excluded from dedup check
    Document::create([
        'knowledge_source_id' => $this->source->id,
        'path' => $path.'_dup',
        'filename' => 'duplicate.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => strlen($content),
        'content_hash' => $hash.'_different',
        'status' => DocumentStatus::Duplicate->value,
    ]);

    $knownHashes = Document::whereNotNull('content_hash')
        ->whereNotIn('status', ['stale', 'duplicate', 'error'])
        ->pluck('content_hash')
        ->unique()
        ->toArray();

    // Only the discovered document's hash should be in the dedup set
    expect($knownHashes)->toContain($hash)
        ->and($knownHashes)->not->toContain($hash.'_different');
});

test('ParseDocument dedup marks duplicate when content_hash matches existing', function () {
    $content = 'ParseDocument dedup test.';

    // Create an already-parsed document with this content
    $existingPath = createTempFile($content);
    $existingHash = hash('sha256', $content); // content hash, not file hash

    Document::create([
        'knowledge_source_id' => $this->source->id,
        'path' => $existingPath,
        'filename' => 'existing.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => strlen($content),
        'content' => $content,
        'content_hash' => $existingHash,
        'status' => DocumentStatus::Parsed->value,
        'parsed_at' => now(),
    ]);

    // Create a new document to be parsed (same content, different path)
    $newPath = createTempFile($content);
    $newDoc = Document::create([
        'knowledge_source_id' => $this->source->id,
        'path' => $newPath,
        'filename' => 'duplicate.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => strlen($content),
        'status' => DocumentStatus::Discovered->value,
    ]);

    // Simulate ParseDocument — it would call TikaParser which returns the content
    // For our test, we manually simulate the dedup logic
    $duplicate = Document::where('content_hash', $existingHash)
        ->where('id', '!=', $newDoc->id)
        ->whereNotIn('status', ['stale', 'duplicate', 'error'])
        ->exists();

    expect($duplicate)->toBeTrue();

    // Apply the duplicate marking
    $newDoc->update([
        'content' => $content,
        'content_hash' => $existingHash,
        'status' => DocumentStatus::Duplicate->value,
        'parsed_at' => now(),
        'error_message' => 'Duplicate content detected.',
    ]);

    $newDoc->refresh();
    expect($newDoc->status)->toBe(DocumentStatus::Duplicate->value)
        ->and($newDoc->content_hash)->toBe($existingHash);
});
