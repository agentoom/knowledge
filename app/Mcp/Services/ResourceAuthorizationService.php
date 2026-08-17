<?php

namespace App\Mcp\Services;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Authorizes MCP resource reads.
 *
 * Web requests are authenticated by McpApiGuard, which stamps
 * `mcp_api_key_id` on the request. The attribute is used as the fast path;
 * when absent (local/stdio transport, or long-running processes where the
 * guard cached a user for an earlier request), the bearer token is resolved
 * directly. Local transport has no bearer token and is allowed — matching
 * the existing tools. Web requests additionally require the `mcp:use` scope
 * (or the `admin:*` wildcard), a non-expired key, and — when an allowlist is
 * configured — membership of the requested namespace.
 */
class ResourceAuthorizationService
{
    public function __construct(private readonly Request $request) {}

    /**
     * Whether the current request may read resources, optionally scoped to a
     * single knowledge namespace.
     */
    public function authorize(?string $namespace = null): bool
    {
        $apiKey = $this->resolveApiKey();

        // Local/stdio transport presents no credential at all — allow it,
        // matching the existing MCP tools. Any presented-but-invalid
        // credential (missing key row, wrong hash, expired) denies.
        if ($apiKey === null && ! $this->presentedCredential()) {
            return true;
        }

        if ($apiKey === null || $apiKey->isExpired()) {
            return false;
        }

        /** @var array<int, string> $scopes */
        $scopes = $apiKey->scopes ?? [];

        if (! in_array('admin:*', $scopes, true) && ! in_array('mcp:use', $scopes, true)) {
            return false;
        }

        if ($namespace === null) {
            return true;
        }

        /** @var array<int, string> $allowed */
        $allowed = $apiKey->knowledge_namespaces ?? [];

        if ($allowed === [] || in_array('*', $allowed, true)) {
            return true;
        }

        return in_array($namespace, $allowed, true);
    }

    /**
     * Whether a web credential (guard attribute or bearer token) was presented.
     */
    private function presentedCredential(): bool
    {
        return $this->request->attributes->has('mcp_api_key_id')
            || $this->request->bearerToken() !== null;
    }

    private function resolveApiKey(): ?ApiKey
    {
        $apiKeyId = $this->request->attributes->get('mcp_api_key_id');

        if ($apiKeyId !== null) {
            return ApiKey::find($apiKeyId);
        }

        $token = $this->request->bearerToken();

        if ($token === null || $token === '') {
            return null;
        }

        $keyPrefix = substr($token, 0, 8);

        $candidates = ApiKey::where('key_prefix', $keyPrefix)
            ->where(function ($query) {
                $query->where('expires_at', '>', now())
                    ->orWhereNull('expires_at');
            })
            ->get();

        /** @var ApiKey|null $apiKey */
        $apiKey = $candidates->first(fn (ApiKey $key) => Hash::check($token, $key->key));

        return $apiKey;
    }
}
