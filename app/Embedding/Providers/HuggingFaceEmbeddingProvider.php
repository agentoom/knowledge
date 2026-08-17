<?php

namespace App\Embedding\Providers;

use App\Contracts\EmbeddingProvider;
use App\Embedding\Concerns\NormalizesEmbeddings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client for a self-hosted HuggingFace-compatible embedding endpoint
 * (e.g. Text Embeddings Inference / sentence-transformers).
 *
 * Accepts the common single-vector response shapes:
 *  - flat array: `[0.1, 0.2, ...]`
 *  - nested array: `[[0.1, 0.2, ...]]`
 *  - OpenAI-style object: `{"data": [{"embedding": [...]}]}`
 *  - `{"embeddings": [[...]]}`
 */
class HuggingFaceEmbeddingProvider implements EmbeddingProvider
{
    use NormalizesEmbeddings;

    private string $endpoint;

    private string $model;

    private int $dimensions;

    private string $apiToken;

    /**
     * @param  array{endpoint?: string, model?: string, dimensions?: int, api_token?: string}  $config
     */
    public function __construct(array $config = [])
    {
        $this->endpoint = rtrim((string) ($config['endpoint'] ?? config('services.huggingface.endpoint', 'http://tei:8080/embed')), '/');
        $this->model = (string) ($config['model'] ?? config('services.huggingface.model', 'sentence-transformers/all-MiniLM-L6-v2'));
        $this->dimensions = (int) (($config['dimensions'] ?? config('services.huggingface.embedding_dimensions', 384)) ?: 384);
        $this->apiToken = (string) ($config['api_token'] ?? config('services.huggingface.api_token', ''));
    }

    public function embed(string $text, string $inputType = 'search_document'): array
    {
        $request = Http::timeout(60)
            ->connectTimeout(5)
            ->asJson();

        if ($this->apiToken !== '') {
            $request = $request->withToken($this->apiToken);
        }

        $response = $request->post($this->endpoint, [
            'inputs' => $text,
            'model' => $this->model,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("HuggingFace embedding request failed: HTTP {$response->status()} — {$response->body()}");
        }

        $vector = $this->normalizeResponse($response->json());

        if (count($vector) !== $this->dimensions) {
            throw new RuntimeException(
                "HuggingFace embedding dimension mismatch: expected {$this->dimensions}, got ".count($vector).'.'
            );
        }

        return $vector;
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    /**
     * @return array<int, float>
     */
    private function normalizeResponse(mixed $data): array
    {
        if (is_array($data) && isset($data['data'][0]['embedding'])) {
            return $this->toFloatVector($data['data'][0]['embedding']);
        }

        if (is_array($data) && isset($data['embeddings'][0])) {
            return $this->toFloatVector($data['embeddings'][0]);
        }

        if (is_array($data) && array_is_list($data) && isset($data[0]) && is_array($data[0]) && array_is_list($data[0])) {
            return $this->toFloatVector($data[0]);
        }

        // Only a flat list of scalars is a valid single-vector response.
        if (is_array($data) && array_is_list($data) && array_filter($data, 'is_scalar') === $data) {
            return $this->toFloatVector($data);
        }

        throw new RuntimeException('Embedding response is missing the vector data.');
    }
}
