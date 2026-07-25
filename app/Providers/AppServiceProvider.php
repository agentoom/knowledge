<?php

namespace App\Providers;

use App\Auth\Guards\McpApiGuard;
use App\Events\SettingsChanged;
use App\Knowledge\Models\KnowledgeSource;
use App\Listeners\LogSettingsChange;
use App\Models\ApiKey;
use App\Observers\KnowledgeSourceObserver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerAuthGuard();
        $this->registerEventListeners();

        KnowledgeSource::observe(KnowledgeSourceObserver::class);
    }

    private function registerAuthGuard(): void
    {
        Auth::extend('mcp_api', function ($app, $name, array $config) {
            return new McpApiGuard($app['request']);
        });

        Auth::provider('api_keys', function ($app, array $config) {
            return new class($app['hash'], ApiKey::class) extends EloquentUserProvider
            {
                public function retrieveById($identifier)
                {
                    return null;
                }

                public function retrieveByToken($identifier, $token)
                {
                    return null;
                }

                public function updateRememberToken($user, $token): void {}
            };
        });
    }

    private function registerEventListeners(): void
    {
        Event::listen(SettingsChanged::class, LogSettingsChange::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
