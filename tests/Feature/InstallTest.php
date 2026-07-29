<?php

use App\Enums\Role;
use App\Models\User;

beforeEach(function () {
    // Ensure env vars are cleared so we test the random-password path by default
    config(['app.env' => 'testing']);
});

test('install route creates superadmin when no admin exists', function () {
    $response = $this->get(route('install'));

    $response->assertOk();
    $response->assertSee('Installation complete');
    $response->assertSee('admin@agentoom.com');

    $admin = User::where('role', Role::Admin->value)->first();
    expect($admin)->not->toBeNull();
    expect($admin->email)->toBe('admin@agentoom.com');
    // Random password should be at least 8 chars and not the old hardcoded value
    expect(strlen($admin->password))->toBeGreaterThanOrEqual(8);
    expect(password_verify('changeme', $admin->password))->toBeFalse();
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
    $admin = User::where('role', Role::Admin->value)->first();
    expect($admin)->not->toBeNull();
});

test('knowledge:install command creates superadmin when user does not exist', function () {
    $this->artisan('knowledge:install')
        ->expectsOutput('Superadmin user created successfully.')
        ->assertExitCode(0);

    $admin = User::where('role', Role::Admin->value)->first();
    expect($admin)->not->toBeNull();
    expect($admin->email)->toBe('admin@agentoom.com');
});

test('knowledge:install command skips when superadmin already exists', function () {
    User::factory()->create([
        'email' => 'admin@agentoom.com',
        'role' => Role::Admin,
    ]);

    $this->artisan('knowledge:install')
        ->expectsOutput('Superadmin user already exists.')
        ->assertExitCode(0);

    $this->assertEquals(1, User::count());
});

test('knowledge:install uses ADMIN_EMAIL and ADMIN_PASSWORD env vars when set', function () {
    config(['app.env' => 'testing']);
    putenv('ADMIN_EMAIL=ci@example.com');
    putenv('ADMIN_PASSWORD=ci-secure-password');

    // Force re-resolve env since it may be cached
    app()->forgetInstance('env');

    $this->artisan('knowledge:install')
        ->expectsOutput('Superadmin user created successfully.')
        ->assertExitCode(0);

    $admin = User::where('role', Role::Admin->value)->first();
    expect($admin)->not->toBeNull();
    expect($admin->email)->toBe('ci@example.com');
    expect(password_verify('ci-secure-password', $admin->password))->toBeTrue();

    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');
});

test('database seeder creates admin user with random password when env vars are not set', function () {
    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');

    $this->artisan('db:seed', ['--class' => 'DatabaseSeeder'])
        ->assertExitCode(0);

    $admin = User::where('role', Role::Admin->value)->first();
    expect($admin)->not->toBeNull();
    expect($admin->email)->toBe('admin@agentoom.com');
    // Password should be hashed and not the old hardcoded value
    expect(password_verify('changeme', $admin->password))->toBeFalse();
    // Password hash should be at least 60 chars (bcrypt)
    expect(strlen($admin->password))->toBeGreaterThanOrEqual(60);

    // Verify the password file was written
    $this->assertFileExists(storage_path('app/initial-admin-password.txt'));
    $savedPassword = trim(file_get_contents(storage_path('app/initial-admin-password.txt')));
    expect(password_verify($savedPassword, $admin->password))->toBeTrue();

    unlink(storage_path('app/initial-admin-password.txt'));
});

test('database seeder uses ADMIN_EMAIL and ADMIN_PASSWORD env vars when set', function () {
    putenv('ADMIN_EMAIL=custom-admin@test.com');
    putenv('ADMIN_PASSWORD=my-test-password');

    // Delete any existing admin so the seeder creates a fresh one
    User::where('role', Role::Admin->value)->delete();

    $this->artisan('db:seed', ['--class' => 'DatabaseSeeder'])
        ->assertExitCode(0);

    $admin = User::where('role', Role::Admin->value)->first();
    expect($admin)->not->toBeNull();
    expect($admin->email)->toBe('custom-admin@test.com');
    expect(password_verify('my-test-password', $admin->password))->toBeTrue();

    putenv('ADMIN_EMAIL');
    putenv('ADMIN_PASSWORD');
});
