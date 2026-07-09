<?php

namespace App\Policies;

use App\Enums\Role;
use App\Knowledge\Models\KnowledgeSource;
use App\Models\User;

class KnowledgeSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, KnowledgeSource $knowledgeSource): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Admin || $user->role === Role::Operator;
    }

    public function update(User $user, KnowledgeSource $knowledgeSource): bool
    {
        return $user->role === Role::Admin || $user->role === Role::Operator;
    }

    public function delete(User $user, KnowledgeSource $knowledgeSource): bool
    {
        return $user->role === Role::Admin;
    }

    public function restore(User $user, KnowledgeSource $knowledgeSource): bool
    {
        return $user->role === Role::Admin;
    }

    public function forceDelete(User $user, KnowledgeSource $knowledgeSource): bool
    {
        return $user->role === Role::Admin;
    }
}
