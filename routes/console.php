<?php

use App\DocumentPipeline\Services\PipelineOrchestrator;
use App\Knowledge\Models\KnowledgeSource;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    $orchestrator = app(PipelineOrchestrator::class);

    KnowledgeSource::where('is_active', true)->each(function ($source) use ($orchestrator) {
        $orchestrator->run($source);
    });
})->hourly();
