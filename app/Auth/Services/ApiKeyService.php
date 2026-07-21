<?php

namespace App\Auth\Services;

use App\Models\ApiKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiKeyService
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function create(string $name, int $userId, array $scopes = [], \DateTimeInterface|string|null $expiresAt = null): ApiKey
    {
        $plainKey = Str::random(64);

        $apiKey = ApiKey::create([
            'user_id' => $userId,
            'name' => $name,
            'key' => Hash::make($plainKey),
            'key_prefix' => substr($plainKey, 0, 8),
            'scopes' => $scopes,
            'expires_at' => $expiresAt,
        ]);

        /** @var ApiKey $apiKey */
        $apiKey->plainKey = $plainKey;

        return $apiKey;
    }

    public function revoke(ApiKey $apiKey): void
    {
        $apiKey->delete();
    }

    public function rotate(ApiKey $apiKey): ApiKey
    {
        return DB::transaction(function () use ($apiKey) {
            /** @var string|array<int, string>|null $storedScopes */
            $storedScopes = $apiKey->scopes;
            $scopes = is_array($storedScopes) ? $storedScopes : [];

            $newKey = $this->create(
                name: $apiKey->name,
                userId: $apiKey->user_id,
                scopes: $scopes,
                expiresAt: $apiKey->expires_at,
            );

            $this->revoke($apiKey);

            return $newKey;
        });
    }
}
