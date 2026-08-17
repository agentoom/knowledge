<?php

use App\DocumentPipeline\Parsers\TikaParser;
use App\DocumentPipeline\Services\OcrService;
use App\Jobs\DocumentPipeline\ParseDocument;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Minimal 1x1 transparent PNG for image fixtures.
 */
const OCR_TEST_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

beforeEach(function () {
    config()->set('knowledge.ocr_enabled', true);
    config()->set('knowledge.ocr_min_content_chars', 20);

    // Availability is cached for 5 minutes; reset between tests.
    Cache::forget('ocr:available');
});

afterEach(function () {
    Cache::forget('ocr:available');
});

function ocrPngFixture(): string
{
    $path = sys_get_temp_dir().'/ocr_test_'.uniqid().'.png';
    file_put_contents($path, base64_decode(OCR_TEST_PNG_BASE64, true));

    return $path;
}

function ocrTextFixture(): string
{
    $path = sys_get_temp_dir().'/ocr_test_'.uniqid().'.txt';
    file_put_contents($path, 'Plain text content for the non-image path.');

    return $path;
}

function fakeTikaAndOcr(string $tikaContent, string $ocrText, int $ocrStatus = 200): void
{
    Http::fake([
        'http://tika:9998/tika' => Http::response([
            'X-TIKA:content' => $tikaContent,
            'Content-Type' => 'image/png',
        ], 200),
        'http://ocr:8000/health' => Http::response(['status' => 'ok'], 200),
        'http://ocr:8000/ocr' => Http::response(['text' => $ocrText], $ocrStatus),
    ]);
}

test('image with near-empty tika text is replaced by ocr text and marked', function () {
    $path = ocrPngFixture();

    fakeTikaAndOcr(' ', "OCR extracted line one\nOCR extracted line two");

    $result = app(TikaParser::class)->parse($path);

    expect($result['content'])->toBe("OCR extracted line one\nOCR extracted line two")
        ->and($result['metadata']['ocr_applied'])->toBeTrue()
        ->and($result['metadata']['Content-Type'])->toBe('image/png');

    Http::assertSent(function ($request) use ($path) {
        return $request->url() === 'http://ocr:8000/ocr'
            && $request->method() === 'POST'
            && $request->body() === file_get_contents($path)
            && str_starts_with($request->header('Content-Type')[0] ?? '', 'image/png');
    });
});

test('ocr does not run at or above the minimum content threshold', function () {
    config()->set('knowledge.ocr_min_content_chars', 20);

    $path = ocrPngFixture();

    Http::fake([
        'http://tika:9998/tika' => Http::response(['X-TIKA:content' => str_repeat('a', 20)], 200),
        'http://ocr:8000/health' => Http::response(['status' => 'ok'], 200),
        'http://ocr:8000/ocr' => Http::response(['text' => 'should not happen'], 200),
    ]);

    $result = app(TikaParser::class)->parse($path);

    expect($result['content'])->toBe(str_repeat('a', 20))
        ->and($result['metadata']['ocr_applied'] ?? false)->toBeFalse();

    Http::assertNotSent(fn ($request) => $request->url() === 'http://ocr:8000/ocr');
});

test('ocr runs when tika content is below the minimum threshold', function () {
    config()->set('knowledge.ocr_min_content_chars', 20);

    $path = ocrPngFixture();

    fakeTikaAndOcr(str_repeat('a', 19), 'OCR fallback text');

    $result = app(TikaParser::class)->parse($path);

    expect($result['content'])->toBe('OCR fallback text')
        ->and($result['metadata']['ocr_applied'])->toBeTrue();
});

test('non-image files skip ocr entirely', function () {
    $path = ocrTextFixture();

    Http::fake([
        'http://tika:9998/tika' => Http::response(['X-TIKA:content' => 'Only tika text.'], 200),
        'http://ocr:8000/health' => Http::response(['status' => 'ok'], 200),
        'http://ocr:8000/ocr' => Http::response(['text' => 'should not happen'], 200),
    ]);

    $result = app(TikaParser::class)->parse($path);

    expect($result['content'])->toBe('Only tika text.')
        ->and($result['metadata']['ocr_applied'] ?? false)->toBeFalse();

    Http::assertNotSent(fn ($request) => $request->url() === 'http://ocr:8000/ocr');
});

test('non-empty tika text skips ocr', function () {
    $path = ocrPngFixture();

    fakeTikaAndOcr('A sufficiently long tika extraction of image metadata and text.', '');

    $result = app(TikaParser::class)->parse($path);

    expect($result['content'])->toBe('A sufficiently long tika extraction of image metadata and text.')
        ->and($result['metadata']['ocr_applied'] ?? false)->toBeFalse();

    Http::assertNotSent(fn ($request) => $request->url() === 'http://ocr:8000/ocr');
});

test('ocr failure degrades to the original tika result without throwing', function () {
    $path = ocrPngFixture();

    fakeTikaAndOcr('   ', 'will fail', 500);

    $result = app(TikaParser::class)->parse($path);

    expect($result['content'])->toBe('')
        ->and($result['metadata']['ocr_applied'] ?? false)->toBeFalse();
});

test('empty ocr text keeps the original tika result', function () {
    $path = ocrPngFixture();

    fakeTikaAndOcr('   ', '');

    $result = app(TikaParser::class)->parse($path);

    expect($result['content'])->toBe('')
        ->and($result['metadata']['ocr_applied'] ?? false)->toBeFalse();
});

test('unavailable ocr service skips the ocr call', function () {
    $path = ocrPngFixture();

    Http::fake([
        'http://tika:9998/tika' => Http::response(['X-TIKA:content' => ' '], 200),
        'http://ocr:8000/health' => Http::response(['status' => 'down'], 503),
        'http://ocr:8000/ocr' => Http::response(['text' => 'should not happen'], 200),
    ]);

    $result = app(TikaParser::class)->parse($path);

    expect($result['content'])->toBe('')
        ->and($result['metadata']['ocr_applied'] ?? false)->toBeFalse();

    Http::assertNotSent(fn ($request) => $request->url() === 'http://ocr:8000/ocr');
});

test('ocr availability is cached and refreshable', function () {
    Http::fake([
        'http://ocr:8000/health' => Http::response(['status' => 'ok'], 200),
    ]);

    $service = app(OcrService::class);

    expect($service->isAvailable())->toBeTrue()
        ->and($service->isAvailable())->toBeTrue();

    Http::assertSentCount(1);

    $service->refresh();

    expect(Cache::has('ocr:available'))->toBeFalse();
});

test('parse document stores ocr text and its sha256 content hash', function () {
    $path = ocrPngFixture();

    $source = KnowledgeSource::factory()->create(['is_active' => true]);
    $document = Document::factory()->create([
        'knowledge_source_id' => $source->id,
        'path' => $path,
        'filename' => 'scanned-invoice.png',
        'status' => 'discovered',
    ]);

    fakeTikaAndOcr(' ', 'Invoice total: 1,234 USD');

    (new ParseDocument($document->id))->handle(app(TikaParser::class));

    $document->refresh();

    expect($document->status)->toBe('parsed')
        ->and($document->content)->toBe('Invoice total: 1,234 USD')
        ->and($document->content_hash)->toBe(hash('sha256', 'Invoice total: 1,234 USD'));
});

test('deduplication applies to ocr output', function () {
    $path = ocrPngFixture();

    $source = KnowledgeSource::factory()->create(['is_active' => true]);

    // Existing document with the same OCR-derived content.
    Document::factory()->create([
        'knowledge_source_id' => $source->id,
        'filename' => 'already-indexed.png',
        'status' => 'indexed',
        'content' => 'Duplicate scanned page',
        'content_hash' => hash('sha256', 'Duplicate scanned page'),
    ]);

    $document = Document::factory()->create([
        'knowledge_source_id' => $source->id,
        'path' => $path,
        'filename' => 'scanned-copy.png',
        'status' => 'discovered',
    ]);

    fakeTikaAndOcr(' ', 'Duplicate scanned page');

    (new ParseDocument($document->id))->handle(app(TikaParser::class));

    $document->refresh();

    expect($document->status)->toBe('duplicate')
        ->and($document->error_message)->toContain('Duplicate content detected');
});
