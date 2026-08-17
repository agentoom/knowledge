<?php

namespace App\Observers;

use App\Models\ApiKey;
use App\Services\ActivityLogger;

class ApiKeyObserver
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function created(ApiKey $apiKey): void
    {
        $this->logger->safeRecord('api_key.created', $apiKey, $this->safeAttributes($apiKey));
    }

    public function updated(ApiKey $apiKey): void
    {
        // The high-volume last_used_at write performed by MCP authentication
        // is not audit-worthy, and the hashed key material must never persist.
        $changed = array_diff_key($apiKey->getChanges(), array_flip(['last_used_at', 'key']));

        if ($changed === []) {
            return;
        }

        // Resolve through getAttribute() so array casts (scopes, namespaces)
        // are audited as arrays instead of raw JSON strings.
        $changes = [];

        foreach (array_keys($changed) as $field) {
            $changes[$field] = $apiKey->getAttribute($field);
        }

        $this->logger->safeRecord('api_key.updated', $apiKey, $changes);
    }

    public function deleted(ApiKey $apiKey): void
    {
        $this->logger->safeRecord('api_key.deleted', $apiKey, $this->safeAttributes($apiKey));
    }

    /**
     * Log only safe fields; never persist key, plainKey, or hashes.
     *
     * @return array<string, mixed>
     */
    private function safeAttributes(ApiKey $apiKey): array
    {
        $safe = ['id', 'user_id', 'name', 'key_prefix', 'scopes', 'knowledge_namespaces', 'expires_at'];
        $attributes = [];

        foreach ($safe as $field) {
            // getAttribute() applies casts so array fields are persisted as
            // real arrays instead of their raw JSON string representation.
            $attributes[$field] = $apiKey->getAttribute($field);
        }

        return $attributes;
    }
}
