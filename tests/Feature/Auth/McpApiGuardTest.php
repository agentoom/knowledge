<?php

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('request without token returns 401', function () {
    $response = $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'method' => 'tools/call',
        'params' => [
            'name' => 'search_knowledge',
            'arguments' => ['query' => 'test'],
        ],
    ]);

    $response->assertStatus(401);
});

test('valid token authenticates successfully', function () {
    $plainKey = 'test-api-key-'.bin2hex(random_bytes(16));
    $apiKey = ApiKey::create([
        'name' => 'Test Key',
        'key' => Hash::make($plainKey),
        'key_prefix' => substr($plainKey, 0, 8),
        'scopes' => ['mcp:use'],
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainKey)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'search_knowledge',
                'arguments' => ['query' => 'test'],
            ],
        ]);

    $response->assertStatus(202);
});

test('expired token is rejected', function () {
    $plainKey = 'expired-key-'.bin2hex(random_bytes(16));
    ApiKey::create([
        'name' => 'Expired Key',
        'key' => Hash::make($plainKey),
        'key_prefix' => substr($plainKey, 0, 8),
        'scopes' => ['mcp:use'],
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainKey)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'search_knowledge',
                'arguments' => ['query' => 'test'],
            ],
        ]);

    $response->assertStatus(401);
});

test('service account api key authenticates without user', function () {
    $plainKey = 'service-key-'.bin2hex(random_bytes(16));
    ApiKey::create([
        'name' => 'Service Account',
        'key' => Hash::make($plainKey),
        'key_prefix' => substr($plainKey, 0, 8),
        'scopes' => ['mcp:use'],
        'user_id' => null,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainKey)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'search_knowledge',
                'arguments' => ['query' => 'test'],
            ],
        ]);

    $response->assertStatus(202);
});

test('api key with user authenticates and returns user', function () {
    $user = User::factory()->create();
    $plainKey = 'user-key-'.bin2hex(random_bytes(16));
    ApiKey::create([
        'name' => 'User Key',
        'key' => Hash::make($plainKey),
        'key_prefix' => substr($plainKey, 0, 8),
        'scopes' => ['mcp:use'],
        'user_id' => $user->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$plainKey)
        ->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'search_knowledge',
                'arguments' => ['query' => 'test'],
            ],
        ]);

    $response->assertStatus(202);
    expect(auth('mcp_api')->user())->not->toBeNull();
});
