<?php

namespace App\Observers;

use App\Knowledge\Models\KnowledgeSource;
use App\Providers\Filesystem\FilesystemProvider;
use App\Providers\Json\JsonProvider;
use App\Providers\Sql\SqlProvider;
use App\Providers\VectorStore\SemanticProvider;
use App\Providers\Web\WebProvider;
use App\Providers\Yaml\YamlProvider;
use Illuminate\Support\Facades\Config;

class KnowledgeSourceObserver
{
    public function created(KnowledgeSource $source): void
    {
        $this->ensureProviderExists($source);
    }

    public function updated(KnowledgeSource $source): void
    {
        $this->ensureProviderExists($source);
    }

    public function deleted(KnowledgeSource $source): void
    {
        $source->providers()->delete();
    }

    private function ensureProviderExists(KnowledgeSource $source): void
    {
        if (! $source->is_active) {
            $source->providers()->update(['status' => 'inactive']);

            return;
        }

        $providerClass = $this->resolveProviderClass($source->provider_type);

        if (! $providerClass) {
            return;
        }

        $source->providers()->updateOrCreate(
            ['class' => $providerClass],
            [
                'name' => $source->name.' Provider',
                'type' => $source->provider_type,
                'status' => 'active',
                'metadata' => [
                    'namespace' => $source->namespace,
                    'capabilities' => $this->resolveCapabilities($source->provider_type),
                ],
            ]
        );
    }

    private function resolveProviderClass(string $type): ?string
    {
        $builtIn = $this->builtInProviders();
        $custom = Config::get('knowledge.custom_providers', []);

        return $builtIn[$type] ?? $custom[$type] ?? null;
    }

    /**
     * @return array<string, class-string>
     */
    private function builtInProviders(): array
    {
        return [
            'filesystem' => FilesystemProvider::class,
            'sql' => SqlProvider::class,
            'yaml' => YamlProvider::class,
            'json' => JsonProvider::class,
            'web' => WebProvider::class,
            'vector_store' => SemanticProvider::class,
        ];
    }

    private function resolveCapabilities(string $type): array
    {
        return match ($type) {
            'filesystem' => ['search', 'list_resources'],
            'sql' => ['search', 'schema_query', 'structured_filter'],
            'yaml', 'json' => ['search', 'list_resources', 'structured_filter'],
            'web' => ['search', 'list_resources', 'crawl'],
            'vector_store' => ['search', 'semantic_search'],
            default => ['search'],
        };
    }
}
