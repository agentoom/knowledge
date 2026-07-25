<?php

namespace App\Livewire\Admin\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Component;

class Roles extends Component
{
    public function render(): View
    {
        return view('livewire.admin.users.roles', [
            'roles' => Role::cases(),
            'usersByRole' => collect(Role::cases())->mapWithKeys(fn (Role $role) => [
                $role->value => User::where('role', $role->value)->orderBy('name')->get(),
            ])->all(),
            'unassignedUsers' => User::whereNull('role')->orWhere('role', '')->orderBy('name')->get(),
        ])->layout('layouts.app', ['header' => 'Roles']);
    }
}
