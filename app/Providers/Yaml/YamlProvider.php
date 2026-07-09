<?php

namespace App\Providers\Yaml;

use App\Contracts\KnowledgeProvider;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use Symfony\Component\Yaml\Yaml;

class YamlProvider implements KnowledgeProvider
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            capabilities: ['search', 'list_resources', 'schema_query'],
            searchableResources: $this->getResourceKeys(),
            searchableFields: ['key', 'value'],
            namespace: 'yaml',
            supportedOperations: ['full_text', 'structured_filter'],
        );
    }

    public function search(SearchQuery $query): SearchResult
    {
        $files = $this->discoverFiles();
        $items = [];

        foreach ($files as $file) {
            if (! file_exists($file)) {
                continue;
            }

            $content = Yaml::parseFile($file);

            if (! is_array($content)) {
                continue;
            }

            foreach ($content as $key => $value) {
                $searchTarget = strtolower(json_encode([$key => $value]) ?: '');

                if ($query->query === '' || str_contains($searchTarget, strtolower($query->query))) {
                    $items[] = [
                        'file' => basename($file),
                        'key' => $key,
                        'value' => $value,
                    ];
                }
            }
        }

        return new SearchResult(
            items: array_slice($items, 0, $query->maxResults),
            totalCount: count($items),
            providerName: 'yaml',
        );
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, $this->metadata()->supportedOperations);
    }

    /**
     * @return array<int, string>
     */
    private function getResourceKeys(): array
    {
        $keys = [];

        foreach ($this->discoverFiles() as $file) {
            if (! file_exists($file)) {
                continue;
            }

            $content = Yaml::parseFile($file);

            if (is_array($content)) {
                $keys = array_merge($keys, array_keys($content));
            }
        }

        return array_unique($keys);
    }

    /**
     * @return array<int, string>
     */
    private function discoverFiles(): array
    {
        if (! is_dir($this->basePath)) {
            return [];
        }

        return glob($this->basePath.'/*.{yml,yaml}', GLOB_BRACE) ?: [];
    }
}
