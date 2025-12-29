<?php

namespace App\Policies;

use App\Framework\AuthenticatedUser;
use App\Models\Model;

class ProductPolicy extends BasePolicy
{
    public function create(AuthenticatedUser $user): bool
    {
        return $this->isEditor($user);
    }

    public function update(AuthenticatedUser $user, Model $model): bool
    {
        return $this->isEditor($user) || $this->owns($user, $model);
    }

    public function delete(AuthenticatedUser $user, Model $model): bool
    {
        return $this->isAdmin($user) || $this->owns($user, $model);
    }
}