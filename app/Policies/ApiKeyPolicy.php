<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ApiKey;
use App\Models\User;

class ApiKeyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ApiKey $apiKey): bool
    {
        return $user->role === Role::Admin || $apiKey->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Admin || $user->role === Role::Operator;
    }

    public function update(User $user, ApiKey $apiKey): bool
    {
        return $user->role === Role::Admin || $apiKey->user_id === $user->id;
    }

    public function delete(User $user, ApiKey $apiKey): bool
    {
        return $user->role === Role::Admin || $apiKey->user_id === $user->id;
    }

    public function restore(User $user, ApiKey $apiKey): bool
    {
        return $user->role === Role::Admin;
    }

    public function forceDelete(User $user, ApiKey $apiKey): bool
    {
        return $user->role === Role::Admin;
    }
}
