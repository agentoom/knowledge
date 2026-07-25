<?php

namespace App\Jobs\DocumentPipeline;

use App\Contracts\FilesystemKnowledgeProvider;
use App\Knowledge\Enums\ProviderType;
use App\Knowledge\Models\KnowledgeSource;
use App\Knowledge\Services\ProviderManager;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DiscoverDocuments implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public function __construct(
        public readonly int $knowledgeSourceId,
    ) {}

    public function handle(ProviderManager $providerManager): void
    {
        $source = KnowledgeSource::findOrFail($this->knowledgeSourceId);

        $providerModels = $providerManager->getByType($source->provider_type);

        if ($providerModels->isEmpty()) {
            Log::warning('No providers found for knowledge source.', [
                'source_id' => $source->id,
                'provider_type' => $source->provider_type,
            ]);

            return;
        }

        $allFiles = [];

        foreach ($providerModels as $providerModel) {
            $provider = $providerModel->toKnowledgeProvider();

            if ($provider === null || ! method_exists($provider, 'discoverFiles')) {
                continue;
            }

            $files = $provider->discoverFiles();

            foreach ($files as $file) {
                $allFiles[] = [
                    'path' => $file['path'],
                    'filename' => $file['filename'],
                    'size_bytes' => $file['size'] ?? 0,
                    'status' => 'discovered',
                    'metadata' => json_encode([
                        'provider_class' => $providerModel->class,
                        'namespace' => is_array($providerModel->metadata)
                            ? ($providerModel->metadata['namespace'] ?? null)
                            : null,
                    ]),
                ];
            }

            // Ensure the provider directory exists
            if ($provider instanceof FilesystemKnowledgeProvider) {
                $type = ProviderType::tryFrom($source->provider_type);

                if ($type && $type->isFilesystemBacked()) {
                    $basePath = $type->canonicalPath($source->namespace);

                    if (! is_dir($basePath)) {
                        mkdir($basePath, 0755, true);
                    }
                }
            }
        }

        // Batch upsert: all files in one query
        if (! empty($allFiles)) {
            $source->documents()->upsert(
                $allFiles,
                ['path'],          // unique key
                ['filename', 'size_bytes', 'status', 'metadata']  // columns to update
            );
        }
    }
}
