<?php

namespace App\Console\Commands\Knowledge;

use App\DocumentPipeline\Services\PipelineOrchestrator;
use App\Knowledge\Enums\DocumentStatus;
use App\Knowledge\Models\Document;
use Illuminate\Console\Command;

class ReprocessDocuments extends Command
{
    protected $signature = 'knowledge:documents:reprocess
                            {--source= : The knowledge source ID, slug, or namespace to scope the reprocess}
                            {--limit=100 : Maximum number of error documents to reprocess}';

    protected $description = 'Reprocess documents stuck in the error state';

    public function handle(PipelineOrchestrator $orchestrator): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $source = $this->option('source');

        $query = Document::query()->where('status', DocumentStatus::Error->value);

        $query->whereHas('knowledgeSource', function ($q) use ($source) {
            $q->where('provider_type', '!=', 'web');

            if ($source !== null && $source !== '') {
                $q->where(function ($inner) use ($source) {
                    // Only compare against the id column for numeric arguments;
                    // PostgreSQL rejects bigint = text comparisons otherwise.
                    if (is_numeric($source)) {
                        $inner->where('id', (int) $source);
                    }

                    $inner->orWhere('namespace', $source)
                        ->orWhere('slug', $source);
                });
            }
        });

        $documents = $query->with('knowledgeSource')->limit($limit)->get();

        if ($documents->isEmpty()) {
            $this->info('No error documents found to reprocess.');

            return self::SUCCESS;
        }

        $queued = 0;
        $skipped = 0;

        foreach ($documents as $document) {
            if ($document->status !== DocumentStatus::Error->value
                || $document->knowledgeSource?->provider_type === 'web') {
                $skipped++;

                continue;
            }

            $orchestrator->reprocess($document);
            $queued++;
        }

        $this->info("Queued {$queued} document(s) for reprocessing; skipped {$skipped}.");

        return self::SUCCESS;
    }
}
