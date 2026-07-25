<?php

namespace App\Knowledge\Enums;

use App\DocumentPipeline\Services\TikaService;
use Illuminate\Support\Str;

enum ProviderType: string
{
    case Filesystem = 'filesystem';
    case Sql = 'sql';
    case Yaml = 'yaml';
    case Json = 'json';
    case Markdown = 'markdown';
    case Rest = 'rest';
    case Mcp = 'mcp';
    case Website = 'website';

    /**
     * Check if this provider type is filesystem-backed (has a directory on disk).
     */
    public function isFilesystemBacked(): bool
    {
        return in_array($this, [
            self::Filesystem,
            self::Yaml,
            self::Json,
            self::Markdown,
        ]);
    }

    /**
     * Compute the canonical directory path for this provider type and namespace.
     */
    public function canonicalPath(string $namespace): string
    {
        $basePath = config('knowledge.base_path');

        return match ($this) {
            self::Filesystem => $basePath.'/filesystem/'.
                Str::slug($namespace),
            self::Yaml => $basePath.'/yaml/'.
                Str::slug($namespace),
            self::Json => $basePath.'/json/'.
                Str::slug($namespace),
            self::Markdown => $basePath.'/markdown/'.
                Str::slug($namespace),
            default => '',
        };
    }

    /**
     * Get the list of allowed file extensions for this provider type.
     *
     * When Tika is reachable, the filesystem provider accepts all formats
     * Tika can parse (office documents, images with OCR, ebooks, archives,
     * email, etc.). Otherwise it falls back to a PHP-friendly subset.
     *
     * @return array<int, string>
     */
    public function allowedExtensions(): array
    {
        return match ($this) {
            self::Filesystem => $this->resolveFilesystemExtensions(),
            self::Yaml => config('knowledge.allowed_extensions.yaml', ['yml', 'yaml']),
            self::Json => config('knowledge.allowed_extensions.json', ['json']),
            self::Markdown => config('knowledge.allowed_extensions.markdown', ['md', 'mdx']),
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function resolveFilesystemExtensions(): array
    {
        $tikaAvailable = app(TikaService::class)->isAvailable();

        if ($tikaAvailable) {
            return config('knowledge.tika_enabled_extensions', []);
        }

        return config('knowledge.allowed_extensions.filesystem', [
            'txt', 'md', 'pdf', 'doc', 'docx', 'html', 'csv', 'json', 'yml', 'yaml', 'xml',
        ]);
    }

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Filesystem => 'Filesystem',
            self::Sql => 'SQL Database',
            self::Yaml => 'YAML Files',
            self::Json => 'JSON Files',
            self::Markdown => 'Markdown Files',
            self::Website => 'Web Crawler',
            default => ucfirst($this->value),
        };
    }

    /**
     * Description of accepted file types for display in the UI.
     */
    public function acceptedFormatsLabel(): string
    {
        if (! $this->isFilesystemBacked()) {
            return '';
        }

        $extensions = $this->allowedExtensions();

        if ($this === self::Filesystem && count($extensions) > 12) {
            return '40+ formats — docs, spreadsheets, slides, PDF, ebooks, email, images (metadata only)';
        }

        return implode(', ', $extensions);
    }
}
