<?php

namespace App\Console\Commands\Knowledge;

use App\Knowledge\Services\ProviderManager;
use Illuminate\Console\Command;

class ProvidersList extends Command
{
    protected $signature = 'knowledge:providers:list';

    protected $description = 'List all registered knowledge providers';

    public function handle(ProviderManager $manager): int
    {
        $providers = $manager->all();

        if ($providers->isEmpty()) {
            $this->info('No providers registered.');

            return self::SUCCESS;
        }

        $rows = $providers->map(function ($provider) {
            $meta = is_array($provider->metadata) ? $provider->metadata : [];

            return [
                $provider->class,
                $meta['namespace'] ?? 'unknown',
                implode(', ', $meta['capabilities'] ?? []),
                implode(', ', $meta['searchableResources'] ?? []),
            ];
        })->all();

        $this->table(
            ['Class', 'Namespace', 'Capabilities', 'Resources'],
            $rows
        );

        return self::SUCCESS;
    }
}
