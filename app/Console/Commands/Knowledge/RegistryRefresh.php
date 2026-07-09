<?php

namespace App\Console\Commands\Knowledge;

use App\Knowledge\Services\MetadataRegistryService;
use Illuminate\Console\Command;

class RegistryRefresh extends Command
{
    protected $signature = 'knowledge:registry:refresh';

    protected $description = 'Rebuild the metadata registry from all active providers';

    public function handle(MetadataRegistryService $service): int
    {
        $this->info('Building metadata registry...');

        $registry = $service->build();

        $this->info("Registry built successfully. Version: {$registry->version}, Checksum: {$registry->checksum}");

        return self::SUCCESS;
    }
}
