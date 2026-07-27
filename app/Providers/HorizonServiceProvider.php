<?php

namespace App\Providers;

use App\Enums\Role;
use App\Settings\Facades\Settings;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        $alertEmail = Settings::get('notifications.email_address', '');

        if ($alertEmail !== '' && $alertEmail !== null) {
            Horizon::routeMailNotificationsTo($alertEmail);
        }

        $slackWebhook = env('HORIZON_SLACK_WEBHOOK_URL');

        if ($slackWebhook !== null && $slackWebhook !== '') {
            Horizon::routeSlackNotificationsTo($slackWebhook, '#alerts');
        }
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return $user !== null && $user->role === Role::Admin;
        });
    }
}
