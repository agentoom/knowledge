<?php

namespace App\Providers\Filesystem;

use App\Concerns\StoresKnowledgeFiles;
use App\Contracts\FilesystemKnowledgeProvider;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use Illuminate\Support\Str;

class FilesystemProvider implements FilesystemKnowledgeProvider
{
    use StoresKnowledgeFiles;

    private string $basePath;

    /** @var array<int, string> */
    private array $extensions;

    public function __construct(?string $basePath = null, ?array $allowedExtensions = null)
    {
        $this->extensions = $allowedExtensions ?? config('knowledge.allowed_extensions.filesystem', ['txt', 'md', 'pdf', 'doc', 'docx', 'html', 'csv', 'json', 'yml', 'yaml', 'xml']);
        $this->basePath = $basePath ?? self::canonicalPath('default');
    }

    public static function canonicalPath(string $namespace): string
    {
        return config('knowledge.base_path').'/filesystem/'.Str::slug($namespace);
    }

    public function allowedExtensions(): array
    {
        return $this->extensions;
    }

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            capabilities: ['search', 'list_resources', 'upload'],
            searchableResources: ['documents'],
            searchableFields: ['filename', 'content'],
            namespace: 'filesystem',
            supportedOperations: ['full_text', 'semantic'],
        );
    }

    public function search(SearchQuery $query): SearchResult
    {
        $files = $this->discoverFiles();

        $matched = $files->filter(function (array $file) use ($query) {
            if (empty($query->query)) {
                return true;
            }

            return stripos($file['filename'], $query->query) !== false;
        });

        return new SearchResult(
            items: $matched->values()->all(),
            totalCount: $matched->count(),
            providerName: 'filesystem',
        );
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, $this->metadata()->supportedOperations);
    }
}
