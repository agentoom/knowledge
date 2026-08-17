<?php

namespace App\Console\Commands\Knowledge;

use App\DocumentPipeline\Services\PipelineOrchestrator;
use App\Knowledge\Models\KnowledgeSource;
use Illuminate\Console\Command;

class PipelineRun extends Command
{
    protected $signature = 'knowledge:pipeline:run
                            {source? : The knowledge source ID, namespace, or slug to run}';

    protected $description = 'Run the document pipeline to discover and process documents';

    public function handle(PipelineOrchestrator $orchestrator): int
    {
        $query = KnowledgeSource::query()->where('is_active', true);

        if ($source = $this->argument('source')) {
            $query->where(function ($q) use ($source) {
                // Only compare against the id column for numeric arguments;
                // PostgreSQL rejects bigint = text comparisons otherwise.
                if (is_numeric($source)) {
                    $q->where('id', (int) $source);
                }

                $q->orWhere('namespace', $source)
                    ->orWhere('slug', $source);
            });

            $sources = $query->get();

            if ($sources->isEmpty()) {
                $this->error("No active knowledge source found matching '{$source}'.");

                return self::FAILURE;
            }
        } else {
            $sources = $query->get();

            if ($sources->isEmpty()) {
                $this->info('No active knowledge sources found.');

                return self::SUCCESS;
            }
        }

        $count = $sources->count();
        $this->info("Running document pipeline for {$count} knowledge source(s)...");

        foreach ($sources as $source) {
            $this->info("  Processing: {$source->name} (ID: {$source->id}, namespace: {$source->namespace})");
            $orchestrator->run($source);
        }

        $this->info('Pipeline jobs dispatched successfully.');
        $this->info('Run `sail artisan queue:work` if no queue worker is running.');

        return self::SUCCESS;
    }
}
