<?php

namespace App\Providers;

use App\Auth\Guards\McpApiGuard;
use App\Events\RetrievalExecuted;
use App\Events\SettingsChanged;
use App\Knowledge\Models\KnowledgeSource;
use App\Listeners\CheckSearchLatency;
use App\Listeners\LogSettingsChange;
use App\Models\ApiKey;
use App\Observers\KnowledgeSourceObserver;
use App\Settings\Facades\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();

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
        Event::listen(RetrievalExecuted::class, CheckSearchLatency::class);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('mcp-api', function (Request $request) {
            $enabled = (bool) Settings::get('mcp.rate_limiting_enabled', true);

            if (! $enabled) {
                return Limit::none();
            }

            $maxPerMinute = (int) Settings::get(
                'mcp.rate_limit_per_minute',
                (int) env('MCP_RATE_LIMIT_PER_MINUTE', 60),
            );

            // Extract the API key ID directly from the bearer token for reliable
            // per-key rate limiting. The guard user may be cached across test
            // requests by the AuthManager, so we cannot rely on $request->user().
            $token = $request->bearerToken();
            $keyId = $request->ip() ?? 'unknown';

            if ($token !== null && $token !== '') {
                $keyPrefix = substr($token, 0, 8);
                $apiKey = ApiKey::where('key_prefix', $keyPrefix)->first();

                if ($apiKey !== null) {
                    $keyId = 'api_key:'.$apiKey->id;
                }
            }

            return Limit::perMinute($maxPerMinute)->by((string) $keyId);
        });
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
