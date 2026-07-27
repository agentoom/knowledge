<?php

namespace App\Console\Commands\Federation;

use App\Federation\FederationManager;
use App\Models\FederatedServer;
use Illuminate\Console\Command;

class FederationSync extends Command
{
    protected $signature = 'app:federation-sync
                            {--server= : Sync a specific server by ID or name}';

    protected $description = 'Sync capabilities from remote federation servers.';

    public function handle(FederationManager $manager): int
    {
        $serverFilter = $this->option('server');

        $query = FederatedServer::where('is_active', true);

        if ($serverFilter !== null) {
            $query->where(function ($q) use ($serverFilter) {
                $q->where('id', $serverFilter)
                    ->orWhere('name', $serverFilter);
            });
        }

        $servers = $query->get();

        if ($servers->isEmpty()) {
            $this->warn('No active federation servers found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Syncing %d federation server(s)...', $servers->count()));

        $progress = $this->output->createProgressBar($servers->count());

        foreach ($servers as $server) {
            $manager->syncCapabilities($server);
            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);
        $this->info('Federation sync complete.');

        return self::SUCCESS;
    }
}
