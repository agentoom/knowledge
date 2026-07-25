<?php

namespace App\Providers;

use App\Admin\Services\AdminUi;
use App\Contracts\PlannerStrategy;
use App\Contracts\ResultFusionStrategy;
use App\Contracts\SettingsManager as SettingsManagerContract;
use App\DocumentPipeline\Services\ChunkingStrategyRegistry;
use App\Federation\FederationManager;
use App\Planning\Strategies\DefaultPlanner;
use App\Planning\Strategies\FederationPlanner;
use App\Retrieval\Fusion\ReciprocalRankFusion;
use App\Settings\Facades\Settings;
use App\Settings\SettingsManager;
use Illuminate\Support\ServiceProvider;

class KnowledgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('admin-ui', fn () => new AdminUi);
        $this->app->singleton('settings', fn ($app) => new SettingsManager($app['cache.store']));

        $this->app->bind(SettingsManagerContract::class, SettingsManager::class);
        $this->app->bind(PlannerStrategy::class, function ($app): PlannerStrategy {
            $strategy = Settings::get('knowledge.default_planner_strategy', 'federation');

            return match ($strategy) {
                'default' => $app->make(DefaultPlanner::class),
                'federation' => $app->make(FederationPlanner::class),
                default => $app->make(FederationPlanner::class),
            };
        });

        $this->app->bind(ResultFusionStrategy::class, function ($app): ResultFusionStrategy {
            $strategy = Settings::get('knowledge.default_fusion_strategy', 'reciprocal_rank_fusion');

            return match ($strategy) {
                'reciprocal_rank_fusion' => $app->make(ReciprocalRankFusion::class),
                default => $app->make(ReciprocalRankFusion::class),
            };
        });

        $this->app->singleton(ChunkingStrategyRegistry::class);
        $this->app->singleton(FederationManager::class);
    }

    public function boot(): void {}
}
