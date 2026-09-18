<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends BasePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($ability, ['delete', 'forceDelete', 'restore', 'invite', 'manage'], true)) {
            return null;
        }

        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Model $model): bool
    {
        return $user->isAdmin() || $user->is($model);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Model $model): bool
    {
        return $user->isAdmin() || $user->is($model);
    }

    public function delete(User $user, Model $model): bool
    {
        if (! $user->isAdmin() || ! $model instanceof User || $user->is($model)) {
            return false;
        }

        if ($model->role === UserRole::Admin) {
            return User::query()
                ->where('role', UserRole::Admin)
                ->whereKeyNot($model->id)
                ->exists();
        }

        return true;
    }

    public function invite(User $user, Model $model): bool
    {
        return $user->isAdmin()
            && $model instanceof User
            && ! $user->is($model);
    }

    public function manage(User $user, Model $model): bool
    {
        return $user->isAdmin()
            && $model instanceof User
            && ! $user->is($model);
    }
}
