<?php

namespace App\DocumentPipeline\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Detects whether Apache Tika is reachable and ready to parse documents.
 *
 * Caches the result for 5 minutes to avoid hammering the health endpoint
 * on every upload or validation cycle.
 */
class TikaService
{
    private string $endpoint;

    public function __construct()
    {
        $this->endpoint = config('services.tika.endpoint', 'http://tika:9998');
    }

    public function isAvailable(): bool
    {
        return Cache::remember('tika:available', 300, function () {
            try {
                $response = Http::timeout(3)
                    ->withBody('tika', 'text/plain')
                    ->accept('text/plain')
                    ->put("{$this->endpoint}/tika");

                return $response->successful();
            } catch (\Throwable) {
                return false;
            }
        });
    }

    /**
     * Invalidate the cached availability so the next check hits Tika live.
     */
    public function refresh(): void
    {
        Cache::forget('tika:available');
    }
}
