<?php

namespace App\Providers\Json;

use App\Concerns\StoresKnowledgeFiles;
use App\Contracts\FilesystemKnowledgeProvider;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use Illuminate\Support\Str;

class JsonProvider implements FilesystemKnowledgeProvider
{
    use StoresKnowledgeFiles;

    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? self::canonicalPath('default');
    }

    public static function canonicalPath(string $namespace): string
    {
        return config('knowledge.base_path').'/json/'.Str::slug($namespace);
    }

    public function allowedExtensions(): array
    {
        return config('knowledge.allowed_extensions.json', ['json']);
    }

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            capabilities: ['search', 'list_resources', 'schema_query', 'upload'],
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
            if (! file_exists($file['path'])) {
                continue;
            }

            $raw = file_get_contents($file['path']);

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
            if (! file_exists($file['path'])) {
                continue;
            }

            $raw = file_get_contents($file['path']);

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
}
