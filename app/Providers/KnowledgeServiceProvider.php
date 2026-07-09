<?php

namespace App\Providers;

use App\Admin\Services\AdminUi;
use App\Contracts\PlannerStrategy;
use App\Contracts\ResultFusionStrategy;
use App\Contracts\SettingsManager as SettingsManagerContract;
use App\DocumentPipeline\Services\ChunkingStrategyRegistry;
use App\Federation\FederationManager;
use App\Planning\Strategies\FederationPlanner;
use App\Retrieval\Fusion\ReciprocalRankFusion;
use App\Settings\SettingsManager;
use Illuminate\Support\ServiceProvider;

class KnowledgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('admin-ui', fn () => new AdminUi);
        $this->app->singleton('settings', fn ($app) => new SettingsManager($app['cache.store']));

        $this->app->bind(SettingsManagerContract::class, SettingsManager::class);
        $this->app->bind(ResultFusionStrategy::class, ReciprocalRankFusion::class);
        $this->app->singleton(ChunkingStrategyRegistry::class);
        $this->app->singleton(FederationManager::class);
        $this->app->bind(PlannerStrategy::class, FederationPlanner::class);
    }

    public function boot(): void {}
}
