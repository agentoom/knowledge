<?php

use App\Enums\Role;
use App\Models\User;

test('install route creates superadmin when no admin exists', function () {
    $response = $this->get(route('install'));

    $response->assertOk();
    $response->assertSee('Installation complete');

    $this->assertDatabaseHas('users', [
        'email' => 'knowledge@agentoom.com',
        'role' => Role::Admin->value,
    ]);
});

test('install route returns 403 when superadmin already exists', function () {
    User::factory()->create(['role' => Role::Admin]);

    $response = $this->get(route('install'));

    $response->assertForbidden();
    $response->assertSee('already installed');
});

test('install route creates superadmin when only non-admin users exist', function () {
    User::factory()->create(['role' => Role::Viewer]);
    User::factory()->create(['role' => Role::Operator]);

    $response = $this->get(route('install'));

    $response->assertOk();
    $response->assertSee('Installation complete');

    $this->assertEquals(3, User::count());
    $this->assertDatabaseHas('users', [
        'email' => 'knowledge@agentoom.com',
        'role' => Role::Admin->value,
    ]);
});

test('knowledge:install command creates superadmin when user does not exist', function () {
    $this->artisan('knowledge:install')
        ->expectsOutput('Superadmin user created successfully.')
        ->assertExitCode(0);

    $this->assertDatabaseHas('users', [
        'email' => 'knowledge@agentoom.com',
        'role' => Role::Admin->value,
    ]);
});

test('knowledge:install command skips when superadmin already exists', function () {
    User::factory()->create([
        'email' => 'knowledge@agentoom.com',
        'role' => Role::Admin,
    ]);

    $this->artisan('knowledge:install')
        ->expectsOutput('Superadmin user already exists.')
        ->assertExitCode(0);

    $this->assertEquals(1, User::count());
});
