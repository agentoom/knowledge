<?php

namespace App\Providers\Filesystem;

use App\Contracts\KnowledgeProvider;
use App\Knowledge\Models\ProviderMetadata;
use App\Retrieval\Models\SearchQuery;
use App\Retrieval\Models\SearchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class FilesystemProvider implements KnowledgeProvider
{
    private string $basePath;

    /** @var array<int, string> */
    private array $allowedExtensions;

    public function __construct(string $basePath, ?array $allowedExtensions = null)
    {
        $this->basePath = $basePath;
        $this->allowedExtensions = $allowedExtensions ?? ['txt', 'md', 'pdf', 'doc', 'docx', 'html', 'csv'];
    }

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            capabilities: ['search', 'list_resources'],
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

    /**
     * @return Collection<int, array{path: string, filename: string, size: int}>
     */
    public function discoverFiles(): Collection
    {
        if (! is_dir($this->basePath)) {
            return collect();
        }

        $files = File::allFiles($this->basePath);

        return collect($files)
            ->filter(function (\SplFileInfo $file) {
                return in_array(strtolower($file->getExtension()), $this->allowedExtensions);
            })
            ->map(function (\SplFileInfo $file) {
                return [
                    'path' => $file->getRealPath(),
                    'filename' => $file->getFilename(),
                    'size' => $file->getSize(),
                ];
            });
    }
}
