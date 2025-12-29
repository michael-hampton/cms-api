<?php

namespace App\Policies;

use App\Framework\AuthenticatedUser;
use App\Models\Model;

class VideoPolicy extends BasePolicy
{
    public function create(AuthenticatedUser $user): bool
    {
        return true; // All authenticated users can upload videos
    }

    public function update(AuthenticatedUser $user, Model $model): bool
    {
        return $this->isEditor($user) || $this->owns($user, $model);
    }

    public function delete(AuthenticatedUser $user, Model $model): bool
    {
        return $this->isEditor($user) || $this->owns($user, $model);
    }
}