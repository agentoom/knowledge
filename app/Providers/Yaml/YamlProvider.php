<?php

namespace App\Providers\Yaml;

use App\Concerns\StoresKnowledgeFiles;
use App\Contracts\FilesystemKnowledgeProvider;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class YamlProvider implements FilesystemKnowledgeProvider
{
    use StoresKnowledgeFiles;

    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? self::canonicalPath('default');
    }

    public static function canonicalPath(string $namespace): string
    {
        return config('knowledge.base_path').'/yaml/'.Str::slug($namespace);
    }

    public function allowedExtensions(): array
    {
        return config('knowledge.allowed_extensions.yaml', ['yml', 'yaml']);
    }

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            capabilities: ['search', 'list_resources', 'schema_query', 'upload'],
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
            if (! file_exists($file['path'])) {
                continue;
            }

            $content = Yaml::parseFile($file['path']);

            if (! is_array($content)) {
                continue;
            }

            foreach ($content as $key => $value) {
                $searchTarget = strtolower(json_encode([$key => $value]) ?: '');

                if ($query->query === '' || str_contains($searchTarget, strtolower($query->query))) {
                    $items[] = [
                        'file' => $file['filename'],
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
            if (! file_exists($file['path'])) {
                continue;
            }

            $content = Yaml::parseFile($file['path']);

            if (is_array($content)) {
                $keys = array_merge($keys, array_keys($content));
            }
        }

        return array_unique($keys);
    }
}
