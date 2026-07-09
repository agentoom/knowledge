<?php

namespace App\Knowledge\Services;

use App\Knowledge\Models\MetadataRegistry;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Log;

class MetadataRegistryService
{
    public function __construct(
        private readonly ProviderManager $providerManager,
        private readonly Cache $cache,
    ) {}

    public function build(): MetadataRegistry
    {
        $payload = $this->aggregateProviderMetadata();

        $checksum = md5(json_encode($payload));

        $registry = MetadataRegistry::create([
            'payload' => $payload,
            'version' => $this->nextVersion(),
            'checksum' => $checksum,
            'built_at' => now(),
        ]);

        $this->cache->put(
            "metadata_registry:{$checksum}",
            $payload,
            now()->addHours(24)
        );

        Log::info('Metadata registry built successfully.', [
            'version' => $registry->version,
            'checksum' => $registry->checksum,
        ]);

        return $registry;
    }

    public function get(): array
    {
        $latest = MetadataRegistry::latest('id')->first();

        if (! $latest) {
            return [];
        }

        $cached = $this->cache->get("metadata_registry:{$latest->checksum}");

        if (is_array($cached)) {
            return $cached;
        }

        $this->cache->put(
            "metadata_registry:{$latest->checksum}",
            $latest->payload,
            now()->addHours(24)
        );

        return $latest->payload;
    }

    public function getChecksum(): ?string
    {
        return MetadataRegistry::latest('id')->value('checksum');
    }

    /**
     * @return array{providers: array<int, array<string, mixed>>, schemas: array<string, mixed>, resources: array<int, string>, relationships: array<int, string>, namespaces: array<int, string>, capabilities: array<int, string>}
     */
    private function aggregateProviderMetadata(): array
    {
        $providers = $this->providerManager->all();

        $providerMetadata = [];
        $schemas = [];
        $resources = [];
        $relationships = [];
        $namespaces = [];
        $capabilities = [];

        foreach ($providers as $provider) {
            $meta = $provider->metadata ?? [];

            $providerMetadata[] = [
                'class' => $provider->class,
                'priority' => $provider->knowledgeSource?->priority ?? 0,
                'namespace' => $meta['namespace'] ?? null,
                'capabilities' => $meta['capabilities'] ?? [],
                'resources' => $meta['searchableResources'] ?? [],
                'fields' => $meta['searchableFields'] ?? [],
            ];

            $ns = $meta['namespace'] ?? 'unknown';
            $schemas[$ns] = [
                'resources' => $meta['searchableResources'] ?? [],
                'fields' => $meta['searchableFields'] ?? [],
            ];

            $resources = array_unique(array_merge($resources, $meta['searchableResources'] ?? []));
            $relationships = array_unique(array_merge($relationships, $meta['relationships'] ?? []));
            $namespaces[] = $ns;
            $capabilities = array_unique(array_merge($capabilities, $meta['capabilities'] ?? []));
        }

        return [
            'providers' => $providerMetadata,
            'schemas' => $schemas,
            'resources' => array_values($resources),
            'relationships' => array_values($relationships),
            'namespaces' => array_unique($namespaces),
            'capabilities' => array_values($capabilities),
        ];
    }

    private function nextVersion(): int
    {
        $latest = MetadataRegistry::latest('id')->first();

        return $latest ? $latest->version + 1 : 1;
    }
}
