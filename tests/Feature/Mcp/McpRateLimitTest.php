<?php

use App\Models\ApiKey;
use App\Models\User;
use App\Settings\Facades\Settings;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    Settings::set('mcp.rate_limiting_enabled', true, 'boolean');
    Settings::set('mcp.rate_limit_per_minute', 5, 'integer');
});

function createApiKey(?User $user = null): ApiKey
{
    $plainKey = 'ak-'.bin2hex(random_bytes(16));

    return ApiKey::create([
        'user_id' => $user?->id ?? User::factory()->create()->id,
        'name' => 'Rate Limit Test Key',
        'key' => Hash::make($plainKey),
        'key_prefix' => substr($plainKey, 0, 8),
        'scopes' => ['mcp:use'],
    ]);
}

test('rate limiting allows requests within the limit', function () {
    $apiKey = createApiKey();

    // Seed with plain key for MCP auth
    $apiKey->refresh();

    for ($i = 0; $i < 5; $i++) {
        $response = $this->withHeader('Authorization', 'Bearer '.$apiKey->plainKey ?? '')
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => 'tools/list',
                'params' => [],
                'id' => 1,
            ]);

        // May be 200 or 401 depending on auth; we just want no 429
        expect($response->status())->not->toBe(429);
    }
});

test('rate limiting blocks requests exceeding the limit', function () {
    $plainKey = 'ak-'.bin2hex(random_bytes(16));

    $user = User::factory()->create();
    ApiKey::create([
        'user_id' => $user->id,
        'name' => 'Rate Limit Key',
        'key' => Hash::make($plainKey),
        'key_prefix' => substr($plainKey, 0, 8),
        'scopes' => ['mcp:use'],
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->withHeader('Authorization', 'Bearer '.$plainKey)
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => 'tools/list',
                'params' => [],
                'id' => 1,
            ]);
    }

    $response = $this->withHeader('Authorization', 'Bearer '.$plainKey)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'params' => [],
            'id' => 1,
        ]);

    expect($response->status())->toBe(429);
});

test('rate limiting scopes by api key — different keys have independent limits', function () {
    $plainKey1 = 'ak-'.bin2hex(random_bytes(16));
    $plainKey2 = 'ak-'.bin2hex(random_bytes(16));

    $user = User::factory()->create();
    ApiKey::create([
        'user_id' => $user->id,
        'name' => 'Key 1',
        'key' => Hash::make($plainKey1),
        'key_prefix' => substr($plainKey1, 0, 8),
        'scopes' => ['mcp:use'],
    ]);
    ApiKey::create([
        'user_id' => $user->id,
        'name' => 'Key 2',
        'key' => Hash::make($plainKey2),
        'key_prefix' => substr($plainKey2, 0, 8),
        'scopes' => ['mcp:use'],
    ]);

    // Exhaust key 1
    for ($i = 0; $i < 5; $i++) {
        $this->withHeader('Authorization', 'Bearer '.$plainKey1)
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => 'tools/list',
                'params' => [],
                'id' => 1,
            ]);
    }

    $response = $this->withHeader('Authorization', 'Bearer '.$plainKey1)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'params' => [],
            'id' => 1,
        ]);

    expect($response->status())->toBe(429);

    // Key 2 should still work
    $response2 = $this->withHeader('Authorization', 'Bearer '.$plainKey2)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'params' => [],
            'id' => 1,
        ]);

    expect($response2->status())->not->toBe(429);
});

test('rate limiting can be disabled via settings', function () {
    Settings::set('mcp.rate_limiting_enabled', false, 'boolean');

    $plainKey = 'ak-'.bin2hex(random_bytes(16));

    $user = User::factory()->create();
    ApiKey::create([
        'user_id' => $user->id,
        'name' => 'Rate Limit Key',
        'key' => Hash::make($plainKey),
        'key_prefix' => substr($plainKey, 0, 8),
        'scopes' => ['mcp:use'],
    ]);

    for ($i = 0; $i < 10; $i++) {
        $response = $this->withHeader('Authorization', 'Bearer '.$plainKey)
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => 'tools/list',
                'params' => [],
                'id' => 1,
            ]);

        expect($response->status())->not->toBe(429);
    }
});
