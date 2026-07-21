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

        $response = Http::attach(
            'file',
            $contents,
            basename($filePath)
        )->put("{$this->endpoint}/tika", [
            'Accept' => 'application/json',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Tika parsing failed: '.$response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['X-TIKA:content'] ?? $response->body(),
            'metadata' => $data,
        ];
    }

    public function supports(string $mimeType): bool
    {
        return true;
    }
}
