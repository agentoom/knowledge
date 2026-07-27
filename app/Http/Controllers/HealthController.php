<?php

namespace App\Http\Controllers;

use App\VectorStore\Services\VectorStoreManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    /**
     * Return the overall health status of critical services.
     */
    public function check(VectorStoreManager $vectorStoreManager): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'typesense' => $this->checkTypesense($vectorStoreManager),
            'storage' => $this->checkStorage(),
        ];

        $allOk = ! in_array('error', array_column($checks, 'status'), true);
        $status = $allOk ? 'ok' : 'error';

        return response()->json([
            'status' => $status,
            'checks' => array_map(fn (array $check) => $check['status'], $checks),
            'timestamp' => now()->toIso8601String(),
        ], $allOk ? 200 : 503);
    }

    /**
     * @return array{status: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok'];
        } catch (\Throwable) {
            return ['status' => 'error'];
        }
    }

    /**
     * @return array{status: string}
     */
    private function checkRedis(): array
    {
        try {
            $store = config('cache.default', 'redis');
            Cache::store($store)->set('health_check', true, 1);

            return ['status' => 'ok'];
        } catch (\Throwable) {
            return ['status' => 'error'];
        }
    }

    /**
     * @return array{status: string}
     */
    private function checkTypesense(VectorStoreManager $manager): array
    {
        try {
            $healthy = $manager->driver()->healthCheck();

            return ['status' => $healthy ? 'ok' : 'error'];
        } catch (\Throwable) {
            return ['status' => 'error'];
        }
    }

    /**
     * @return array{status: string}
     */
    private function checkStorage(): array
    {
        try {
            $disk = Storage::disk('local');
            $disk->put('health_check.txt', 'ok');
            $disk->delete('health_check.txt');

            return ['status' => 'ok'];
        } catch (\Throwable) {
            return ['status' => 'error'];
        }
    }
}
