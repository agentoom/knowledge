<?php

namespace App\Jobs\DocumentPipeline;

use App\Knowledge\Models\KnowledgeSource;
use App\Knowledge\Services\ProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DiscoverDocuments implements ShouldQueue
{
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

        foreach ($providerModels as $providerModel) {
            $provider = $providerModel->toKnowledgeProvider();

            if ($provider === null || ! method_exists($provider, 'discoverFiles')) {
                continue;
            }

            $files = $provider->discoverFiles();

            foreach ($files as $file) {
                $source->documents()->updateOrCreate(
                    ['path' => $file['path']],
                    [
                        'filename' => $file['filename'],
                        'size_bytes' => $file['size'] ?? 0,
                        'status' => 'discovered',
                        'metadata' => [
                            'provider_class' => $providerModel->class,
                            'namespace' => is_array($providerModel->metadata)
                                ? ($providerModel->metadata['namespace'] ?? null)
                                : null,
                        ],
                    ]
                );
            }
        }
    }
}
