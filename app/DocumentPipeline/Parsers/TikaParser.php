<?php

namespace App\DocumentPipeline\Parsers;

use App\Contracts\DocumentParser;
use Illuminate\Support\Facades\Http;

class TikaParser implements DocumentParser
{
    private string $endpoint;

    public function __construct()
    {
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

        return [
            'content' => $content,
            'metadata' => $data,
        ];
    }

    public function supports(string $mimeType): bool
    {
        return true;
    }
}
