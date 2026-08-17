<?php

namespace App\Embedding\Providers;

use App\Contracts\EmbeddingProvider;
use App\Embedding\Concerns\NormalizesEmbeddings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CohereEmbeddingProvider implements EmbeddingProvider
{
    use NormalizesEmbeddings;

    private string $apiKey;

    private string $baseUrl;

    private string $model;

    private int $dimensions;

    /**
     * @param  array{api_key?: string, base_url?: string, model?: string, dimensions?: int}  $config
     */
    public function __construct(array $config = [])
    {
        $this->apiKey = (string) ($config['api_key'] ?? config('services.cohere.api_key', ''));
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? config('services.cohere.base_url', 'https://api.cohere.com/v1')), '/');
        $this->model = (string) ($config['model'] ?? config('services.cohere.embedding_model', 'embed-english-v3.0'));
        $this->dimensions = (int) (($config['dimensions'] ?? config('services.cohere.embedding_dimensions', 1024)) ?: 1024);
    }

    public function embed(string $text, string $inputType = 'search_document'): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('Cohere API key is not configured. Set COHERE_API_KEY in your environment.');
        }

        $response = Http::timeout(30)
            ->connectTimeout(5)
            ->withToken($this->apiKey)
            ->post("{$this->baseUrl}/embed", [
                'texts' => [$text],
                'model' => $this->model,
                'input_type' => $inputType,
                'embedding_types' => ['float'],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Cohere embedding request failed: HTTP {$response->status()} — {$response->body()}");
        }

        return $this->toFloatVector($response->json('embeddings.float.0'));
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }
}
