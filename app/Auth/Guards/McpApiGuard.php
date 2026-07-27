<?php

namespace App\Auth\Guards;

use App\Models\ApiKey;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class McpApiGuard implements Guard
{
    use GuardHelpers;

    public function __construct(private readonly Request $request) {}

    public function user(): ?Authenticatable
    {
        // Always set the API key ID attribute for rate limiting,
        // even when the guard user is cached (critical for test isolation).
        $token = $this->request->bearerToken();

        if ($token !== null && $token !== '') {
            $keyPrefix = substr($token, 0, 8);
            $apiKeyForRateLimit = ApiKey::where('key_prefix', $keyPrefix)->first();

            if ($apiKeyForRateLimit !== null) {
                $this->request->attributes->set('mcp_api_key_id', $apiKeyForRateLimit->id);
            }
        }

        if ($this->user !== null) {
            return $this->user;
        }

        if ($token === null || $token === '') {
            return null;
        }

        $keyPrefix = substr($token, 0, 8);

        $candidates = ApiKey::where('key_prefix', $keyPrefix)
            ->where(function ($q) {
                $q->where('expires_at', '>', now())
                    ->orWhereNull('expires_at');
            })
            ->get();

        /** @var ApiKey|null $apiKey */
        $apiKey = $candidates->first(fn (ApiKey $key) => Hash::check($token, $key->key));

        if ($apiKey === null) {
            return null;
        }

        $apiKey->update(['last_used_at' => now()]);

        $this->request->attributes->set('mcp_api_key_id', $apiKey->id);

        /** @var Authenticatable|null $authenticatedUser */
        $authenticatedUser = $apiKey->user ?? $this->createServiceAccountUser($apiKey);

        return $this->user = $authenticatedUser;
    }

    private function createServiceAccountUser(ApiKey $apiKey): Authenticatable
    {
        return new class($apiKey) implements Authenticatable
        {
            public function __construct(private readonly ApiKey $apiKey) {}

            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return 'api_key:'.$this->apiKey->id;
            }

            public function getAuthPasswordName()
            {
                return '';
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getRememberToken()
            {
                return '';
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName()
            {
                return '';
            }
        };
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasScope(string $scope): bool
    {
        $token = $this->request->bearerToken();

        if ($token === null || $token === '') {
            return false;
        }

        $keyPrefix = substr($token, 0, 8);

        $candidates = ApiKey::where('key_prefix', $keyPrefix)
            ->where(function ($q) {
                $q->where('expires_at', '>', now())
                    ->orWhereNull('expires_at');
            })
            ->get();

        $apiKey = $candidates->first(fn (ApiKey $key) => Hash::check($token, $key->key));

        if ($apiKey === null) {
            return false;
        }

        /** @var array<int, string> $scopes */
        $scopes = $apiKey->scopes ?? [];

        if (in_array('admin:*', $scopes, true)) {
            return true;
        }

        return in_array($scope, $scopes, true);
    }
}
