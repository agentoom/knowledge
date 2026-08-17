<?php

namespace App\Livewire\Admin\Settings;

use App\Embedding\Services\EmbeddingManager;
use App\Settings\Facades\Settings;
use Illuminate\View\View;
use Livewire\Component;

class Embedding extends Component
{
    public string $provider = 'typesense';

    public string $model = '';

    public string $endpoint = '';

    public int $dimensions = 384;

    public string $connectionStatus = '';

    public bool $testing = false;

    /**
     * @var array<string, string>
     */
    public array $availableProviders = [];

    public function mount(): void
    {
        $this->availableProviders = [
            'typesense' => 'Typesense (Managed)',
            'openai' => 'OpenAI',
            'cohere' => 'Cohere',
            'huggingface' => 'HuggingFace (Local)',
        ];

        $provider = (string) Settings::get('knowledge.embedding_provider', config('knowledge.embedding_provider', 'typesense'));

        $this->provider = $this->availableProviders[$provider] ?? 'typesense';

        $model = (string) Settings::get('knowledge.embedding_model', '');
        $endpoint = (string) Settings::get('knowledge.embedding_endpoint', '');
        $dimensions = (int) Settings::get('knowledge.embedding_dimensions', 0);

        $defaults = $this->providerDefaults($this->provider);

        $this->model = $model !== '' ? $model : $defaults['model'];
        $this->endpoint = $endpoint !== '' ? $endpoint : $defaults['endpoint'];
        $this->dimensions = $dimensions > 0 ? $dimensions : $defaults['dimensions'];
    }

    /**
     * Reset the connection fields to the selected provider's defaults.
     */
    public function changedProvider(): void
    {
        $defaults = $this->providerDefaults($this->provider);

        $this->model = $defaults['model'];
        $this->endpoint = $defaults['endpoint'];
        $this->dimensions = $defaults['dimensions'];
        $this->connectionStatus = '';
    }

    public function save(): void
    {
        $this->validate([
            'provider' => ['required', 'in:typesense,openai,cohere,huggingface'],
            'model' => ['nullable', 'string', 'max:255'],
            'endpoint' => ['nullable', 'string', 'max:500'],
            'dimensions' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        Settings::set('knowledge.embedding_provider', $this->provider, 'string');
        Settings::set('knowledge.embedding_model', $this->model, 'string');
        Settings::set('knowledge.embedding_endpoint', $this->endpoint, 'string');
        Settings::set('knowledge.embedding_dimensions', $this->dimensions, 'integer');

        $this->dispatch('notify', message: 'Embedding settings saved successfully.');
        $this->dispatch('settings-clean');
    }

    public function testConnection(): void
    {
        $this->testing = true;
        $this->connectionStatus = '';

        try {
            if ($this->provider === 'typesense') {
                $this->connectionStatus = 'Managed by the vector store — no external embedding service required.';

                return;
            }

            $vector = app(EmbeddingManager::class)
                ->provider($this->provider)
                ->embed('connection test', 'search_query');

            $this->connectionStatus = 'Success — received '.count($vector).' dimensions.';
        } catch (\Throwable $e) {
            $this->connectionStatus = 'Failed: '.$e->getMessage();
        } finally {
            $this->testing = false;
        }
    }

    public function render(): View
    {
        return view('livewire.admin.settings.embedding');
    }

    /**
     * @return array{model: string, endpoint: string, dimensions: int}
     */
    private function providerDefaults(string $provider): array
    {
        return match ($provider) {
            'openai' => [
                'model' => (string) config('services.openai.embedding_model', 'text-embedding-3-small'),
                'endpoint' => (string) config('services.openai.base_url', 'https://api.openai.com/v1'),
                'dimensions' => (int) config('services.openai.embedding_dimensions', 1536),
            ],
            'cohere' => [
                'model' => (string) config('services.cohere.embedding_model', 'embed-english-v3.0'),
                'endpoint' => (string) config('services.cohere.base_url', 'https://api.cohere.com/v1'),
                'dimensions' => (int) config('services.cohere.embedding_dimensions', 1024),
            ],
            'huggingface' => [
                'model' => (string) config('services.huggingface.model', 'sentence-transformers/all-MiniLM-L6-v2'),
                'endpoint' => (string) config('services.huggingface.endpoint', 'http://tei:8080/embed'),
                'dimensions' => (int) config('services.huggingface.embedding_dimensions', 384),
            ],
            default => ['model' => '', 'endpoint' => '', 'dimensions' => 384],
        };
    }
}
