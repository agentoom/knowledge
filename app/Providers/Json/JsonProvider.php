<?php

namespace App\Providers\Json;

use App\Contracts\KnowledgeProvider;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;

class JsonProvider implements KnowledgeProvider
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
            namespace: 'json',
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

            $raw = file_get_contents($file);

            if ($raw === false) {
                continue;
            }

            $content = json_decode($raw, true);

            if (! is_array($content)) {
                continue;
            }

            if (array_is_list($content)) {
                foreach ($content as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    if ($this->matches($item, $query)) {
                        $items[] = $item;
                    }
                }
            } else {
                if ($this->matches($content, $query)) {
                    $items[] = $content;
                }
            }
        }

        return new SearchResult(
            items: array_slice($items, 0, $query->maxResults),
            totalCount: count($items),
            providerName: 'json',
        );
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, $this->metadata()->supportedOperations);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function matches(array $item, SearchQuery $query): bool
    {
        if ($query->query === '') {
            return true;
        }

        foreach ($item as $value) {
            if (is_string($value) && stripos($value, $query->query) !== false) {
                return true;
            }
        }

        return false;
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

            $raw = file_get_contents($file);

            if ($raw === false) {
                continue;
            }

            $content = json_decode($raw, true);

            if (is_array($content)) {
                if (array_is_list($content)) {
                    foreach ($content as $item) {
                        if (is_array($item)) {
                            $keys = array_merge($keys, array_keys($item));
                        }
                    }
                } else {
                    $keys = array_merge($keys, array_keys($content));
                }
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

        return glob($this->basePath.'/*.json') ?: [];
    }
}
