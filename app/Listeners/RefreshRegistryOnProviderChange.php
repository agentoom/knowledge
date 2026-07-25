<?php

namespace App\Listeners;

use App\Events\ProviderRegistered;
use App\Events\ProviderSynced;
use App\Jobs\RefreshMetadataRegistry;
use Illuminate\Contracts\Events\Dispatcher;

class RefreshRegistryOnProviderChange
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            [ProviderRegistered::class, ProviderSynced::class],
            [self::class, 'handle']
        );
    }

    public function handle(ProviderRegistered|ProviderSynced $event): void
    {
        RefreshMetadataRegistry::dispatch();
    }
}
