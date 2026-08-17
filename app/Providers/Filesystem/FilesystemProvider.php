<?php

namespace App\Providers\Filesystem;

use App\Concerns\StoresKnowledgeFiles;
use App\Contracts\FilesystemKnowledgeProvider;
use App\DocumentPipeline\Services\TikaService;
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
        // When Tika is reachable, the provider expands to accept every format
        // Tika can parse (including images that the OCR fallback makes
        // searchable). Without Tika, only the base list is accepted.
        if (app(TikaService::class)->isAvailable()) {
            return array_values(array_unique(array_merge(
                $this->extensions,
                config('knowledge.tika_enabled_extensions', [])
            )));
        }

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
        $queryStr = $query->query;
        $maxContentSize = config('knowledge.max_content_search_size', 1024 * 1024); // 1MB default

        $matched = $files
            ->map(function (array $file) use ($queryStr, $maxContentSize) {
                $filenameMatch = empty($queryStr)
                    || stripos($file['filename'], $queryStr) !== false;

                $contentSnippet = '';
                $contentMatch = false;

                if (! empty($queryStr) && $file['size'] <= $maxContentSize) {
                    try {
                        $content = @file_get_contents($file['path'], false, null, 0, $maxContentSize);

                        if ($content !== false) {
                            $pos = mb_stripos($content, $queryStr);

                            if ($pos !== false) {
                                $contentMatch = true;
                                $start = max(0, $pos - 80);
                                $snippet = mb_substr($content, $start, 200);
                                $contentSnippet = ($start > 0 ? '…' : '').trim($snippet).((mb_strlen($content) > $start + 200) ? '…' : '');
                            }
                        }
                    } catch (\Throwable) {
                        // Skip files that can't be read
                    }
                }

                if (! $filenameMatch && ! $contentMatch && ! empty($queryStr)) {
                    return null;
                }

                $score = 0.0;

                if ($filenameMatch) {
                    $score += 0.7;
                }

                if ($contentMatch) {
                    $score += 0.3;
                }

                return [
                    'id' => md5($file['path']),
                    'title' => $file['filename'],
                    'content' => $contentSnippet ?: 'File: '.$file['filename'].' ('.round($file['size'] / 1024, 1).' KB)',
                    'score' => $score,
                    'metadata' => [
                        'provider' => 'filesystem',
                        'source' => $file['path'],
                        'size' => $file['size'],
                    ],
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->values()
            ->all();

        return new SearchResult(
            items: $matched,
            totalCount: count($matched),
            providerName: 'filesystem',
        );
    }

    public function supports(string $operation): bool
    {
        return in_array($operation, $this->metadata()->supportedOperations);
    }
}
