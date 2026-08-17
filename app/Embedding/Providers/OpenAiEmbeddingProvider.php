<?php

namespace App\Embedding\Providers;

use App\Contracts\EmbeddingProvider;
use App\Embedding\Concerns\NormalizesEmbeddings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiEmbeddingProvider implements EmbeddingProvider
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
        $this->apiKey = (string) ($config['api_key'] ?? config('services.openai.api_key', ''));
        $this->baseUrl = rtrim((string) ($config['base_url'] ?? config('services.openai.base_url', 'https://api.openai.com/v1')), '/');
        $this->model = (string) ($config['model'] ?? config('services.openai.embedding_model', 'text-embedding-3-small'));
        $this->dimensions = (int) (($config['dimensions'] ?? config('services.openai.embedding_dimensions', 1536)) ?: 1536);
    }

    public function embed(string $text, string $inputType = 'search_document'): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured. Set OPENAI_API_KEY in your environment.');
        }

        $response = Http::timeout(30)
            ->connectTimeout(5)
            ->withToken($this->apiKey)
            ->post("{$this->baseUrl}/embeddings", [
                'input' => $text,
                'model' => $this->model,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("OpenAI embedding request failed: HTTP {$response->status()} — {$response->body()}");
        }

        return $this->toFloatVector($response->json('data.0.embedding'));
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }
}
