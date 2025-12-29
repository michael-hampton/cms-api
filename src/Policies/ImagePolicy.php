<?php

namespace App\Policies;

use App\Framework\AuthenticatedUser;
use App\Models\Model;

class ImagePolicy extends BasePolicy
{
    public function create(AuthenticatedUser $user): bool
    {
        return true; // All authenticated users can upload images
    }

    public function update(AuthenticatedUser $user, Model $model): bool
    {
        return $this->isEditor($user) || $this->owns($user, $model);
    }

    public function delete(AuthenticatedUser $user, Model $model): bool
    {
        return $this->isEditor($user) || $this->owns($user, $model);
    }

    public function bulkDelete(AuthenticatedUser $user): bool
    {
        return $this->isEditor($user);
    }
}