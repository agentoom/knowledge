<?php

use Illuminate\Support\Facades\DB;

test('health endpoint returns 200 when all services are healthy', function () {
    $this->getJson('/health')
        ->assertSuccessful()
        ->assertJson([
            'status' => 'ok',
            'checks' => [
                'database' => 'ok',
                'redis' => 'ok',
                'typesense' => 'ok',
                'storage' => 'ok',
            ],
        ])
        ->assertJsonStructure([
            'status',
            'checks',
            'timestamp',
        ]);
});

test('health endpoint returns 503 when a service is down', function () {
    DB::shouldReceive('connection')->andThrow(new RuntimeException('Connection refused'));

    $this->getJson('/health')
        ->assertStatus(503)
        ->assertJson([
            'status' => 'error',
            'checks' => [
                'database' => 'error',
            ],
        ]);
});

test('health endpoint does not require authentication', function () {
    $this->getJson('/health')->assertSuccessful();
});

test('health endpoint responds quickly', function () {
    $start = microtime(true);

    $this->getJson('/health')->assertSuccessful();

    $duration = (microtime(true) - $start) * 1000;

    expect($duration)->toBeLessThan(1000);
});
