<?php

namespace App\Providers\Filesystem;

use Illuminate\Support\Facades\File;

class FilesystemMetadata
{
    /**
     * @return array{totalFiles: int, totalSizeBytes: int, extensions: array<int, string>}
     */
    public static function forDirectory(string $path): array
    {
        if (! is_dir($path)) {
            return ['totalFiles' => 0, 'totalSizeBytes' => 0, 'extensions' => []];
        }

        $files = File::allFiles($path);

        $extensions = collect($files)
            ->map(fn (\SplFileInfo $file) => strtolower($file->getExtension()))
            ->unique()
            ->values()
            ->all();

        $totalSize = collect($files)->sum(fn (\SplFileInfo $file) => $file->getSize());

        return [
            'totalFiles' => count($files),
            'totalSizeBytes' => $totalSize,
            'extensions' => $extensions,
        ];
    }
}
