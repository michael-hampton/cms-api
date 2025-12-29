<?php

namespace App\Policies;

use App\Framework\AuthenticatedUser;
use App\Models\Model;

class TerritoryPolicy extends BasePolicy
{
    public function create(AuthenticatedUser $user): bool
    {
        return $this->isEditor($user);
    }

    public function update(AuthenticatedUser $user, Model $model): bool
    {
        return $this->isEditor($user);
    }

    public function delete(AuthenticatedUser $user, Model $model): bool
    {
        return $this->isAdmin($user);
    }

    public function bulkDelete(AuthenticatedUser $user): bool
    {
        return $this->isAdmin($user);
    }

    public function bulkActivate(AuthenticatedUser $user): bool
    {
        return $this->isEditor($user);
    }

    public function bulkDeactivate(AuthenticatedUser $user): bool
    {
        return $this->isEditor($user);
    }
}