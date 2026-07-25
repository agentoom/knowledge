<?php

namespace App\Providers\Markdown;

use App\Concerns\StoresKnowledgeFiles;
use App\Contracts\FilesystemKnowledgeProvider;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use Illuminate\Support\Str;

class MarkdownProvider implements FilesystemKnowledgeProvider
{
    use StoresKnowledgeFiles;

    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? self::canonicalPath('default');
    }

    public static function canonicalPath(string $namespace): string
    {
        return config('knowledge.base_path').'/markdown/'.Str::slug($namespace);
    }

    public function allowedExtensions(): array
    {
        return config('knowledge.allowed_extensions.markdown', ['md', 'mdx']);
    }

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            capabilities: ['search', 'list_resources', 'upload'],
            searchableResources: ['documents'],
            searchableFields: ['filename', 'content'],
            namespace: 'markdown',
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

            if (stripos($file['filename'], $query->query) !== false) {
                return true;
            }

            $content = @file_get_contents($file['path']);

            return $content !== false && stripos($content, $query->query) !== false;
        });

        return new SearchResult(
            items: $matched->values()->all(),
            totalCount: $matched->count(),
            providerName: 'markdown',
        );
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, $this->metadata()->supportedOperations);
    }
}
