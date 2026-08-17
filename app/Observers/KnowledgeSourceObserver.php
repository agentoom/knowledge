<?php

namespace App\Observers;

use App\DocumentPipeline\Services\PipelineOrchestrator;
use App\Knowledge\Models\KnowledgeSource;
use App\Providers\Filesystem\FilesystemProvider;
use App\Providers\Json\JsonProvider;
use App\Providers\Markdown\MarkdownProvider;
use App\Providers\Sql\SqlProvider;
use App\Providers\VectorStore\SemanticProvider;
use App\Providers\Web\WebProvider;
use App\Providers\Yaml\YamlProvider;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Config;

class KnowledgeSourceObserver
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function created(KnowledgeSource $source): void
    {
        $this->logger->safeRecord('knowledge_source.created', $source, $this->safeAttributes($source));

        $this->ensureProviderExists($source);
        $this->dispatchPipeline($source);
    }

    public function updated(KnowledgeSource $source): void
    {
        $this->logger->safeRecord('knowledge_source.updated', $source, $this->changedAttributes($source));

        $this->ensureProviderExists($source);

        // Only trigger the pipeline when provider config or active status
        // actually changed — not for cosmetic edits like name/description.
        if ($source->wasChanged(['provider_type', 'provider_config', 'namespace', 'is_active'])) {
            $this->dispatchPipeline($source);
        }
    }

    public function deleted(KnowledgeSource $source): void
    {
        $this->logger->safeRecord('knowledge_source.deleted', $source, $this->safeAttributes($source));

        $source->providers()->delete();
    }

    /**
     * Changed attributes resolved through getAttribute() so array casts come
     * back as real arrays. The raw JSON string form would hide nested keys
     * (e.g. connection.password) from the logger's recursive redaction.
     *
     * @return array<string, mixed>
     */
    private function changedAttributes(KnowledgeSource $source): array
    {
        $changes = [];

        foreach (array_keys($source->getChanges()) as $field) {
            $changes[$field] = $source->getAttribute($field);
        }

        return $changes;
    }

    /**
     * Record only non-sensitive attributes for create/delete events.
     *
     * provider_config is deliberately excluded: SQL dynamic connections
     * store encrypted credentials that must never enter the audit trail.
     *
     * @return array<string, mixed>
     */
    private function safeAttributes(KnowledgeSource $source): array
    {
        return array_intersect_key($source->getAttributes(), array_flip([
            'id',
            'name',
            'slug',
            'namespace',
            'provider_type',
            'description',
            'is_active',
            'priority',
        ]));
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

    private function dispatchPipeline(KnowledgeSource $source): void
    {
        if (! $source->is_active) {
            return;
        }

        app(PipelineOrchestrator::class)->run($source);
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
            'markdown' => MarkdownProvider::class,
            'web' => WebProvider::class,
            'vector_store' => SemanticProvider::class,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolveCapabilities(string $type): array
    {
        return match ($type) {
            'filesystem' => ['search', 'list_resources', 'upload'],
            'sql' => ['search', 'schema_query', 'structured_filter'],
            'yaml', 'json' => ['search', 'list_resources', 'structured_filter', 'upload'],
            'markdown' => ['search', 'list_resources', 'upload'],
            'web' => ['search', 'list_resources', 'crawl'],
            'vector_store' => ['search', 'semantic_search'],
            default => ['search'],
        };
    }
}
