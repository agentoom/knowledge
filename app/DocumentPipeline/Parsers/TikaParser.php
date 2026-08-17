<?php

namespace App\DocumentPipeline\Parsers;

use App\Contracts\DocumentParser;
use App\DocumentPipeline\Services\OcrService;
use Illuminate\Support\Facades\Http;

class TikaParser implements DocumentParser
{
    private string $endpoint;

    public function __construct(
        private readonly OcrService $ocr,
    ) {
        $this->endpoint = config('services.tika.endpoint', 'http://tika:9998');
    }

    public function parse(string $filePath): array
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            throw new \RuntimeException("Unable to read file: {$filePath}");
        }

        // Send the file as the raw PUT body — Tika's /tika endpoint expects
        // the file content as the request body, NOT as multipart/form-data.
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

        $response = Http::withBody($contents, $mimeType)
            ->accept('application/json')
            ->put("{$this->endpoint}/tika");

        if (! $response->successful()) {
            throw new \RuntimeException('Tika parsing failed: '.$response->body());
        }

        $data = $response->json();

        $content = $data['X-TIKA:content'] ?? $response->body();

        // Tika may return HTML-wrapped content even with Accept: application/json.
        // Strip HTML tags and decode entities to get clean plain text.
        $content = strip_tags($content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = trim($content);

        $metadata = is_array($data) ? $data : [];

        // Targeted OCR fallback: only for images whose Tika extraction fell
        // below the configured threshold. OCR text replaces Tika output only
        // when non-empty; metadata is preserved and an OCR marker is added.
        if ($this->shouldRunOcr($content, $filePath)) {
            $ocrText = $this->ocr->recognize($filePath);

            if ($ocrText !== '') {
                $content = $ocrText;
                $metadata['ocr_applied'] = true;
            }
        }

        return [
            'content' => $content,
            'metadata' => $metadata,
        ];
    }

    public function supports(string $mimeType): bool
    {
        return true;
    }

    private function shouldRunOcr(string $content, string $filePath): bool
    {
        if (! (bool) config('knowledge.ocr_enabled', true)) {
            return false;
        }

        if (strlen($content) >= (int) config('knowledge.ocr_min_content_chars', 20)) {
            return false;
        }

        if (! $this->isImage($filePath)) {
            return false;
        }

        return $this->ocr->isAvailable();
    }

    private function isImage(string $filePath): bool
    {
        $mimeType = mime_content_type($filePath) ?: '';

        if (str_starts_with($mimeType, 'image/')) {
            return true;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($extension, (array) config('knowledge.ocr_image_extensions', [
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp',
        ]), true);
    }
}
