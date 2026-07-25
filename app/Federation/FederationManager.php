<?php

namespace App\Federation;

use App\Models\FederatedServer;
use App\Providers\Federation\FederationProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FederationManager
{
    /**
     * Get all active federation providers for remote servers.
     *
     * @return Collection<int, FederationProvider>
     */
    public function getProviders(): Collection
    {
        return Cache::remember('federation_providers', 60, function (): Collection {
            return FederatedServer::query()
                ->where('is_active', true)
                ->orderBy('priority', 'desc')
                ->get()
                ->map(function (FederatedServer $server): ?FederationProvider {
                    $token = $server->auth_token;

                    if ($token === null || $token === '') {
                        return null;
                    }

                    try {
                        $decrypted = decrypt($token);
                    } catch (\Throwable) {
                        Log::warning('FederationManager: failed to decrypt auth token.', [
                            'server_id' => $server->id,
                            'server_name' => $server->name,
                        ]);

                        return null;
                    }

                    return new FederationProvider(
                        endpointUrl: $server->endpoint_url,
                        authToken: $decrypted,
                        serverName: $server->name,
                    );
                })
                ->filter()
                ->values();
        });
    }

    /**
     * Sync capabilities from a remote server.
     */
    public function syncCapabilities(FederatedServer $server): void
    {
        $token = $server->auth_token;

        if ($token === null || $token === '') {
            return;
        }

        try {
            $decrypted = decrypt($token);

            $response = Http::timeout(15)
                ->withToken($decrypted)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($server->endpoint_url, [
                    'jsonrpc' => '2.0',
                    'method' => 'tools/list',
                    'params' => [],
                    'id' => 1,
                ]);

            if ($response->successful()) {
                $body = $response->json();
                $tools = $body['result']['tools'] ?? [];

                $server->update([
                    'remote_capabilities' => [
                        'tools' => array_column($tools, 'name'),
                        'synced_at' => now()->toIso8601String(),
                    ],
                    'last_synced_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('FederationManager: failed to sync capabilities.', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
