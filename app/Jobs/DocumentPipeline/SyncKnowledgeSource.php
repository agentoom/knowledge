<?php

namespace App\Jobs\DocumentPipeline;

use App\DocumentPipeline\Services\PipelineOrchestrator;
use App\Knowledge\Enums\ProviderType;
use App\Knowledge\Models\KnowledgeSource;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Synchronises the filesystem with the documents table for a knowledge source.
 *
 * Scans the provider directory for new or modified files, creates pending
 * Document records, and marks missing-or-stale documents as stale so the
 * pipeline can re-index them.
 *
 * Dispatched after a batch of UI uploads completes so the expensive
 * recursive scan runs in the background.
 */
class SyncKnowledgeSource implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public function __construct(
        public readonly int $knowledgeSourceId,
    ) {}

    public function handle(): void
    {
        $source = KnowledgeSource::find($this->knowledgeSourceId);

        if (! $source || $this->batch()?->cancelled()) {
            return;
        }

        $type = ProviderType::tryFrom($source->provider_type);

        if (! $type || ! $type->isFilesystemBacked()) {
            return;
        }

        $directoryPath = $type->canonicalPath($source->namespace);

        if (! is_dir($directoryPath)) {
            return;
        }

        $maxDepth = (int) config('knowledge.max_scan_depth', 5);
        $allowedExtensions = $type->allowedExtensions();

        $existingPaths = $source->documents()->pluck('path')->toArray();
        $seenPaths = [];
        $records = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directoryPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if ($maxDepth > 0) {
                $relativePath = str_replace($directoryPath, '', $file->getPath());
                $depth = substr_count(trim($relativePath, '/'), '/');

                if ($depth >= $maxDepth) {
                    continue;
                }
            }

            $ext = strtolower($file->getExtension());

            if (! empty($allowedExtensions) && ! in_array($ext, $allowedExtensions, true)) {
                continue;
            }

            $realPath = $file->getRealPath();
            $seenPaths[] = $realPath;

            if (in_array($realPath, $existingPaths, true)) {
                continue;
            }

            $records[] = [
                'knowledge_source_id' => $source->id,
                'path' => $realPath,
                'filename' => $file->getFilename(),
                'mime_type' => mime_content_type($realPath) ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'content_hash' => hash_file('sha256', $realPath) ?: '',
                'status' => 'discovered',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($records)) {
            $source->documents()->insert($records);

            Log::info('SyncKnowledgeSource: discovered new files.', [
                'source_id' => $source->id,
                'count' => count($records),
            ]);

            // Trigger the full pipeline for the newly discovered documents.
            app(PipelineOrchestrator::class)->run($source);
        }

        // Mark documents whose files no longer exist as stale so they
        // don't appear in search results while keeping the record.
        $missingPaths = array_diff($existingPaths, $seenPaths);

        if (! empty($missingPaths)) {
            $source->documents()->whereIn('path', $missingPaths)->update(['status' => 'stale']);

            Log::info('SyncKnowledgeSource: marked missing files as stale.', [
                'source_id' => $source->id,
                'count' => count($missingPaths),
            ]);
        }
    }
}
