<?php

use App\DocumentPipeline\Services\PipelineOrchestrator;
use App\Federation\FederationManager;
use App\Knowledge\Models\KnowledgeSource;
use App\Models\FederatedServer;
use App\Models\RetrievalLog;
use App\Settings\Facades\Settings;
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

// Horizon metrics snapshots — required to populate the Horizon metrics dashboard.
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Federation sync — periodically refresh remote server capabilities.
Schedule::call(function () {
    $manager = app(FederationManager::class);

    FederatedServer::where('is_active', true)->each(function ($server) use ($manager) {
        $manager->syncCapabilities($server);
    });
})->everyFifteenMinutes();

// Retrieval log pruning — prevent unbounded growth of search history.
Schedule::call(function () {
    $ageDays = (int) Settings::get('maintenance.retrieval_log_pruning_age_days', 30);
    $enabled = (bool) Settings::get('maintenance.retrieval_log_pruning_enabled', true);

    if (! $enabled) {
        return;
    }

    RetrievalLog::where('created_at', '<', now()->subDays($ageDays))->delete();
})->daily();
