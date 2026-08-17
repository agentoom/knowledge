<?php

namespace App\DocumentPipeline\Services;

use App\Jobs\DocumentPipeline\CrawlWebSource;
use App\Jobs\DocumentPipeline\DeindexDocument;
use App\Jobs\DocumentPipeline\DiscoverDocuments;
use App\Jobs\DocumentPipeline\NormalizeDocument;
use App\Jobs\DocumentPipeline\ParseDocument;
use App\Knowledge\Enums\DocumentStatus;
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

    /**
     * Reset an errored document and queue it for a fresh parse.
     *
     * Only documents in the error state backed by a non-web source are
     * eligible. Web documents are re-fetched through their source pipeline,
     * never reparsed from URL paths. A safe no-op otherwise — admin clicks
     * must not throw.
     */
    public function reprocess(Document $document): void
    {
        if ($document->status !== DocumentStatus::Error->value) {
            Log::warning('Reprocess skipped: document is not in error state.', [
                'document_id' => $document->id,
            ]);

            return;
        }

        if ($document->knowledgeSource?->provider_type === 'web') {
            Log::warning('Reprocess skipped: web documents are re-fetched through their source pipeline.', [
                'document_id' => $document->id,
            ]);

            return;
        }

        // De-index any previously indexed chunks and drop the local rows so
        // the retry starts from a clean slate.
        $chunkIds = $document->chunks()->pluck('id')->toArray();

        if (! empty($chunkIds)) {
            DeindexDocument::dispatch($chunkIds);
            $document->chunks()->delete();
        }

        $document->update([
            'status' => DocumentStatus::Discovered->value,
            'error_message' => null,
            'parsed_at' => null,
            'chunked_at' => null,
            'indexed_at' => null,
        ]);

        Log::info('Document queued for reprocessing.', [
            'document_id' => $document->id,
        ]);

        ParseDocument::dispatch($document->id);
    }
}
