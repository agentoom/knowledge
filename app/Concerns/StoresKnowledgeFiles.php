<?php

namespace App\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Shared behavior for filesystem-backed knowledge providers.
 *
 * Provides storeFile, sanitizeFilename, discoverFiles (with request-level
 * caching and configurable max scan depth), formatSize, and iconForFile.
 */
trait StoresKnowledgeFiles
{
    /** @var Collection<int, array{path: string, filename: string, size: int}>|null */
    private ?Collection $cachedFiles = null;

    /**
     * Store an uploaded file into the provider's directory.
     */
    public function storeFile(UploadedFile $file): string
    {
        if (! is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }

        $originalName = $this->sanitizeFilename($file->getClientOriginalName());
        $uniqueName = sprintf(
            '%s_%s.%s',
            pathinfo($originalName, PATHINFO_FILENAME),
            now()->timestamp.'_'.bin2hex(random_bytes(4)),
            pathinfo($originalName, PATHINFO_EXTENSION)
        );

        $absolutePath = $this->basePath.'/'.$uniqueName;

        if (method_exists($file, 'getRealPath')) {
            // TemporaryUploadedFile from Livewire — must use rename() because
            // move_uploaded_file() only works for HTTP POST uploads.
            if (! @rename($file->getRealPath(), $absolutePath)) {
                $error = error_get_last();
                throw new \RuntimeException(
                    ($error['message'] ?? 'Could not move file').': '.$file->getRealPath().' → '.$absolutePath
                );
            }
            @chmod($absolutePath, 0664);
        } else {
            $file->move($this->basePath, $uniqueName);
        }

        return $absolutePath;
    }

    /**
     * Discover files in the base path directory, with request-level caching.
     *
     * Respects config('knowledge.max_scan_depth') to limit recursive traversal.
     *
     * @return Collection<int, array{path: string, filename: string, size: int}>
     */
    public function discoverFiles(): Collection
    {
        if ($this->cachedFiles !== null) {
            return $this->cachedFiles;
        }

        if (! is_dir($this->basePath)) {
            return $this->cachedFiles = collect();
        }

        $maxDepth = config('knowledge.max_scan_depth', 5);
        $extensions = $this->allowedExtensions();

        $files = File::allFiles($this->basePath);

        $this->cachedFiles = collect($files)
            ->filter(function (\SplFileInfo $file) use ($extensions, $maxDepth) {
                if ($maxDepth > 0) {
                    $relativePath = str_replace($this->basePath, '', $file->getPath());
                    $depth = substr_count(trim($relativePath, '/'), '/');

                    if ($depth >= $maxDepth) {
                        return false;
                    }
                }

                return in_array(strtolower($file->getExtension()), $extensions);
            })
            ->map(function (\SplFileInfo $file) {
                return [
                    'path' => $file->getRealPath(),
                    'filename' => $file->getFilename(),
                    'size' => $file->getSize(),
                ];
            });

        return $this->cachedFiles;
    }

    /**
     * Strip dangerous characters from a filename.
     */
    public function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[\/\\\\:*?"<>|]/', '_', $name);

        return $name ?: 'untitled';
    }

    /**
     * Format bytes as human-readable string.
     */
    public function formatSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));

        return round($bytes / pow(1024, $i), 1).' '.$units[$i];
    }

    /**
     * Return an inline SVG icon based on the file extension.
     */
    public function iconForFile(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $icons = [
            'md' => '<svg class="size-6 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>',
            'mdx' => '<svg class="size-6 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>',
            'json' => '<svg class="size-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" /></svg>',
            'yml' => '<svg class="size-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" /></svg>',
            'yaml' => '<svg class="size-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" /></svg>',
            'pdf' => '<svg class="size-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>',
            'csv' => '<svg class="size-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>',
            'xml' => '<svg class="size-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" /></svg>',
            'html' => '<svg class="size-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" /></svg>',
        ];

        if (isset($icons[$ext])) {
            return $icons[$ext];
        }

        return '<svg class="size-6 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>';
    }
}
