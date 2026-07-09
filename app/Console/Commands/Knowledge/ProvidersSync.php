<?php

namespace App\Console\Commands\Knowledge;

use App\Events\ProviderSynced;
use App\Knowledge\Models\Provider;
use Illuminate\Console\Command;

class ProvidersSync extends Command
{
    protected $signature = 'knowledge:providers:sync {provider_id? : The ID of a specific provider to sync}';

    protected $description = 'Sync provider metadata and status';

    public function handle(): int
    {
        $query = Provider::query();

        if ($providerId = $this->argument('provider_id')) {
            $query->where('id', $providerId);
        }

        $providers = $query->get();

        if ($providers->isEmpty()) {
            $this->info('No providers to sync.');

            return self::SUCCESS;
        }

        foreach ($providers as $provider) {
            $provider->update([
                'last_synced_at' => now(),
                'status' => 'active',
                'error_message' => null,
            ]);

            ProviderSynced::dispatch($provider);

            $this->info("Synced provider: {$provider->name}");
        }

        return self::SUCCESS;
    }
}
