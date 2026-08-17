<?php

namespace App\DocumentPipeline\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Local OCR fallback for image files.
 *
 * Talks to the internal OCR service (docker/ocr) which exposes:
 *  - POST /ocr   — raw image body, returns `{"text": "..."}`
 *  - GET  /health — readiness probe
 *
 * Availability is cached for 5 minutes (mirroring TikaService). Every
 * failure mode — unavailable, malformed, or empty response — degrades to an
 * empty string with a warning log; callers keep their original result.
 */
class OcrService
{
    private string $endpoint;

    public function __construct()
    {
        $this->endpoint = config('services.ocr.endpoint', 'http://ocr:8000');
    }

    public function isAvailable(): bool
    {
        return Cache::remember('ocr:available', 300, function () {
            try {
                $response = Http::timeout(3)->get("{$this->endpoint}/health");

                return $response->successful();
            } catch (\Throwable) {
                return false;
            }
        });
    }

    /**
     * Recognize text from an image file. Never throws; returns '' on any
     * failure.
     */
    public function recognize(string $filePath): string
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            Log::warning('OcrService: unable to read file.', ['path' => $filePath]);

            return '';
        }

        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        try {
            $response = Http::timeout(120)
                ->connectTimeout(5)
                ->withBody($contents, $mimeType)
                ->accept('application/json')
                ->post("{$this->endpoint}/ocr");

            if (! $response->successful()) {
                Log::warning('OcrService: OCR request failed.', [
                    'path' => $filePath,
                    'status' => $response->status(),
                ]);

                return '';
            }

            $text = trim((string) ($response->json('text') ?? ''));

            if ($text === '') {
                Log::warning('OcrService: OCR returned empty text.', ['path' => $filePath]);

                return '';
            }

            return $text;
        } catch (\Throwable $e) {
            Log::warning('OcrService: OCR request threw.', [
                'path' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Invalidate the cached availability so the next check hits the service live.
     */
    public function refresh(): void
    {
        Cache::forget('ocr:available');
    }
}
