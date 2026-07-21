<?php

namespace App\Livewire\Admin\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public string $userRole = 'viewer';

    public ?int $editingId = null;

    public string $editName = '';

    public string $editEmail = '';

    public string $editPassword = '';

    public string $editPasswordConfirmation = '';

    public string $editRole = 'viewer';

    public function create(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'passwordConfirmation' => 'required|same:password',
            'userRole' => 'required|in:admin,operator,viewer',
        ]);

        User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role' => $this->userRole,
        ]);

        $this->reset(['name', 'email', 'password', 'passwordConfirmation', 'userRole', 'showCreateModal']);
        session()->flash('status', 'User created successfully.');
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->editName = $user->name;
        $this->editEmail = $user->email;
        /** @var Role|null $role */
        $role = $user->role;
        $this->editRole = $role?->value ?? 'viewer';
        $this->showEditModal = true;
    }

    public function update(): void
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editEmail' => "required|email|unique:users,email,{$this->editingId}",
            'editPassword' => 'nullable|string|min:8',
            'editPasswordConfirmation' => 'nullable|required_with:editPassword|same:editPassword',
            'editRole' => 'required|in:admin,operator,viewer',
        ]);

        $user = User::findOrFail($this->editingId);

        $data = [
            'name' => $this->editName,
            'email' => $this->editEmail,
            'role' => $this->editRole,
        ];

        if ($this->editPassword !== '') {
            $data['password'] = Hash::make($this->editPassword);
        }

        $user->update($data);

        $this->reset(['editingId', 'editName', 'editEmail', 'editPassword', 'editPasswordConfirmation', 'editRole', 'showEditModal']);
        session()->flash('status', 'User updated successfully.');
    }

    public function delete(int $id): void
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            session()->flash('error', 'You cannot delete your own account.');

            return;
        }

        $user->delete();
        session()->flash('status', 'User deleted successfully.');
    }

    public function updateRole(int $userId, string $role): void
    {
        $user = User::findOrFail($userId);
        $user->update(['role' => $role]);
        session()->flash('status', "Role updated for {$user->name}.");
    }

    public function render(): View
    {
        return view('livewire.admin.users.index', [
            'users' => User::orderBy('name')->paginate(15),
            'roles' => Role::cases(),
        ])->layout('layouts.app', ['header' => 'Users']);
    }
}
