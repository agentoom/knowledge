<?php

namespace App\DocumentPipeline\Services;

use App\Jobs\DocumentPipeline\CrawlWebSource;
use App\Jobs\DocumentPipeline\DiscoverDocuments;
use App\Jobs\DocumentPipeline\NormalizeDocument;
use App\Jobs\DocumentPipeline\ParseDocument;
use App\Knowledge\Models\Document;
use App\Knowledge\Models\KnowledgeSource;
use App\Providers\Web\CrawlConfig;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class PipelineOrchestrator
{
    public function run(KnowledgeSource $source): void
    {
        Log::info('Starting document pipeline for source.', [
            'source_id' => $source->id,
            'source_name' => $source->name,
        ]);

        // Web sources with crawl config use the recursive crawler
        if ($source->provider_type === 'web') {
            $config = CrawlConfig::fromConfig($source->provider_config);

            if ($config->isCrawlEnabled()) {
                CrawlWebSource::dispatch(
                    knowledgeSourceId: $source->id,
                    urls: $config->seedUrls,
                );

                return;
            }
        }

        Bus::batch([
            new DiscoverDocuments($source->id),
        ])->name('Pipeline: '.$source->name)
            ->then(function () use ($source) {
                $documents = $source->documents()
                    ->where('status', 'discovered')
                    ->get();

                if ($documents->isEmpty()) {
                    return;
                }

                $parseJobs = $documents->map(fn (Document $doc) => new ParseDocument($doc->id))->all();

                Bus::batch($parseJobs)
                    ->name('Parse: '.$source->name)
                    ->then(function () use ($source, $documents) {
                        $normalizeJobs = $documents->map(fn (Document $doc) => new NormalizeDocument($doc->id))->all();

                        Bus::batch($normalizeJobs)
                            ->name('Normalize: '.$source->name)
                            ->allowFailures()
                            ->dispatch();
                    })
                    ->allowFailures()
                    ->dispatch();
            })
            ->catch(function ($batch, $e) {
                Log::error('Pipeline batch failed.', [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);
            })
            ->dispatch();
    }

    public function reindex(Document $document): void
    {
        Log::info('Reindexing document.', [
            'document_id' => $document->id,
            'filename' => $document->filename,
        ]);

        ParseDocument::dispatch($document->id);
    }
}
