<?php

use App\Enums\Role;
use App\Livewire\Admin\Users\Index;
use App\Models\User;
use Livewire\Livewire;

test('user management page lists all users', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->count(3)->create(['role' => 'viewer']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee($admin->name)
        ->assertSee($admin->email);
});

test('admin can create a new user', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showCreateModal', true)
        ->set('name', 'Jane Doe')
        ->set('email', 'jane@example.com')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->set('userRole', 'operator')
        ->call('create')
        ->assertHasNoErrors()
        ->assertSee('User created successfully.');

    $user = User::where('email', 'jane@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->role)->toBe(Role::Operator);
});

test('create user validation requires all fields', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showCreateModal', true)
        ->call('create')
        ->assertHasErrors(['name', 'email', 'password', 'passwordConfirmation']);
});

test('create user validates duplicate email', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showCreateModal', true)
        ->set('name', 'Test User')
        ->set('email', 'taken@example.com')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->call('create')
        ->assertHasErrors(['email']);
});

test('create user validates password length', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showCreateModal', true)
        ->set('name', 'Short Pw')
        ->set('email', 'short@example.com')
        ->set('password', '123')
        ->set('passwordConfirmation', '123')
        ->call('create')
        ->assertHasErrors(['password']);
});

test('create user validates password confirmation match', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showCreateModal', true)
        ->set('name', 'Mismatch')
        ->set('email', 'mismatch@example.com')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'different')
        ->call('create')
        ->assertHasErrors(['passwordConfirmation']);
});

test('admin can edit an existing user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'viewer']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('edit', $user->id)
        ->assertSet('editingId', $user->id)
        ->assertSet('editName', $user->name)
        ->assertSet('editEmail', $user->email)
        ->set('editName', 'Updated Name')
        ->set('editRole', 'operator')
        ->call('update')
        ->assertHasNoErrors()
        ->assertSee('User updated successfully.');

    $user->refresh();
    expect($user->name)->toBe('Updated Name')
        ->and($user->role)->toBe(Role::Operator);
});

test('edit user validates required fields', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'viewer']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('edit', $user->id)
        ->set('editName', '')
        ->set('editEmail', '')
        ->call('update')
        ->assertHasErrors(['editName', 'editEmail']);
});

test('edit user validates unique email excluding current user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user1 = User::factory()->create(['email' => 'first@example.com']);
    $user2 = User::factory()->create(['email' => 'second@example.com']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('edit', $user1->id)
        ->set('editEmail', 'second@example.com')
        ->call('update')
        ->assertHasErrors(['editEmail']);
});

test('admin can delete a user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'viewer']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('delete', $user->id)
        ->assertSee('User deleted successfully.');

    expect(User::find($user->id))->toBeNull();
});

test('user cannot delete themselves', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('delete', $admin->id)
        ->assertSee('You cannot delete your own account.');

    expect(User::find($admin->id))->not->toBeNull();
});

test('create user validates role field', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('showCreateModal', true)
        ->set('name', 'Bad Role')
        ->set('email', 'badrole@example.com')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->set('userRole', 'invalid_role')
        ->call('create')
        ->assertHasErrors(['userRole']);
});

test('viewer user cannot access admin pages', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);

    $this->actingAs($viewer)
        ->get('/admin/dashboard')
        ->assertForbidden();
});

test('operator user can access admin pages', function () {
    $operator = User::factory()->create(['role' => 'operator']);

    $this->actingAs($operator)
        ->get('/admin/dashboard')
        ->assertOk();
});

test('admin user can access admin pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertOk();
});

test('user with unauthorized role cannot access admin pages', function () {
    $user = User::factory()->create(['role' => 'viewer']);

    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertForbidden();
});

test('unauthenticated user is redirected from admin pages', function () {
    $this->get('/admin/dashboard')
        ->assertRedirect('/login');
});
