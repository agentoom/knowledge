<?php

namespace App\Embedding\Services;

use App\Contracts\EmbeddingProvider;
use App\Embedding\Providers\CohereEmbeddingProvider;
use App\Embedding\Providers\HuggingFaceEmbeddingProvider;
use App\Embedding\Providers\OpenAiEmbeddingProvider;
use App\Embedding\Providers\TypesenseProvider;
use App\Settings\Facades\Settings;
use InvalidArgumentException;

/**
 * Resolves the active embedding provider.
 *
 * The selected provider key is stored through the settings facade
 * (`knowledge.embedding_provider`, default `typesense`). Non-secret
 * connection values (model, endpoint, dimensions) may be overridden from
 * settings; API secrets always come from environment/config and are never
 * persisted in the database.
 */
class EmbeddingManager
{
    public function provider(?string $name = null): EmbeddingProvider
    {
        $name ??= $this->activeProvider();

        return match ($name) {
            'typesense' => new TypesenseProvider,
            'openai' => new OpenAiEmbeddingProvider($this->resolvedConfig('openai', 'https://api.openai.com/v1', 'text-embedding-3-small', 1536)),
            'cohere' => new CohereEmbeddingProvider($this->resolvedConfig('cohere', 'https://api.cohere.com/v1', 'embed-english-v3.0', 1024)),
            'huggingface' => new HuggingFaceEmbeddingProvider($this->resolvedConfig('huggingface', 'http://tei:8080/embed', 'sentence-transformers/all-MiniLM-L6-v2', 384)),
            default => throw new InvalidArgumentException("Embedding provider [{$name}] is not supported."),
        };
    }

    /**
     * The currently active embedding provider key.
     */
    public function activeProvider(): string
    {
        return (string) Settings::get('knowledge.embedding_provider', config('knowledge.embedding_provider', 'typesense'));
    }

    /**
     * Whether the active provider is the vector store's managed mode.
     */
    public function isManaged(): bool
    {
        return $this->activeProvider() === 'typesense';
    }

    /**
     * Merge environment-backed defaults with settings overrides for the
     * non-secret connection values (model, endpoint, dimensions).
     *
     * @return array{api_key: string, base_url: string, endpoint: string, model: string, dimensions: int, api_token: string}
     */
    private function resolvedConfig(string $provider, string $defaultEndpoint, string $defaultModel, int $defaultDimensions): array
    {
        $settings = Settings::getMany([
            'knowledge.embedding_model',
            'knowledge.embedding_endpoint',
            'knowledge.embedding_dimensions',
        ], [
            'knowledge.embedding_model' => $defaultModel,
            'knowledge.embedding_endpoint' => $defaultEndpoint,
            'knowledge.embedding_dimensions' => $defaultDimensions,
        ]);

        $model = (string) ($settings['knowledge.embedding_model'] ?? '');
        $endpoint = (string) ($settings['knowledge.embedding_endpoint'] ?? '');
        $dimensions = (int) ($settings['knowledge.embedding_dimensions'] ?? 0);

        if ($model === '') {
            $model = (string) (
                config("services.{$provider}.embedding_model")
                ?? config("services.{$provider}.model")
                ?? $defaultModel
            );
        }

        if ($endpoint === '') {
            $endpoint = (string) (
                config("services.{$provider}.endpoint")
                ?? config("services.{$provider}.base_url")
                ?? $defaultEndpoint
            );
        }

        if ($dimensions <= 0) {
            $dimensions = (int) config("services.{$provider}.embedding_dimensions", $defaultDimensions);
        }

        return [
            'api_key' => (string) config("services.{$provider}.api_key", ''),
            'base_url' => $endpoint,
            'endpoint' => $endpoint,
            'model' => $model,
            'dimensions' => $dimensions,
            'api_token' => (string) config("services.{$provider}.api_token", ''),
        ];
    }
}
