<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

/**
 * Extended contract for knowledge providers backed by a filesystem directory.
 *
 * These providers can discover files on disk AND accept uploads through the UI.
 */
interface FilesystemKnowledgeProvider extends KnowledgeProvider
{
    /**
     * Compute the canonical directory path for a given namespace.
     */
    public static function canonicalPath(string $namespace): string;

    /**
     * Discover all files in the provider's directory.
     *
     * @return Collection<int, array{path: string, filename: string, size: int}>
     */
    public function discoverFiles(): Collection;

    /**
     * Get the list of file extensions this provider handles.
     *
     * @return array<int, string>
     */
    public function allowedExtensions(): array;

    /**
     * Store an uploaded file into the provider's directory.
     *
     * Returns the absolute path to the stored file.
     */
    public function storeFile(UploadedFile $file): string;
}
