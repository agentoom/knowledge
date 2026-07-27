<?php

use App\Enums\Role;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('users without admin or operator role see styled access denied page', function () {
    $user = User::factory()->create(['role' => Role::Viewer]);

    $response = actingAs($user)->get(route('admin.dashboard'));

    $response->assertForbidden();
    $response->assertSee('Access Denied');
    $response->assertSee('Log out');
    $response->assertSee($user->name);
    $response->assertSee($user->email);
});

test('access denied page shows logout form', function () {
    $user = User::factory()->create(['role' => Role::Viewer]);

    $response = actingAs($user)->get(route('admin.dashboard'));

    $response->assertForbidden();
    $response->assertSee('Log out');

    // Verify the logout form is present
    $response->assertSee(route('logout'), false);
    $response->assertSee('_token');
});

test('access denied page shows login button for guests', function () {
    $response = $this->get(route('admin.dashboard'));

    // Unauthenticated users get redirected to login, not 403
    $response->assertRedirect(route('login'));
});

test('admin users can access dashboard normally', function () {
    $user = User::factory()->create(['role' => Role::Admin]);

    $response = actingAs($user)->get(route('admin.dashboard'));

    $response->assertSuccessful();
    $response->assertSee('Admin Dashboard');
});

test('operator users can access dashboard normally', function () {
    $user = User::factory()->create(['role' => Role::Operator]);

    $response = actingAs($user)->get(route('admin.dashboard'));

    $response->assertSuccessful();
    $response->assertSee('Admin Dashboard');
});
