<?php

namespace App\Jobs;

use App\Knowledge\Services\MetadataRegistryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshMetadataRegistry implements ShouldQueue
{
    use Queueable;

    public function handle(MetadataRegistryService $service): void
    {
        $service->build();
    }
}
