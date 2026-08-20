<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OdometerReadingPolicy extends BasePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return null;
    }

    public function create(User $user): bool
    {
        return $user->canManage();
    }

    public function override(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
