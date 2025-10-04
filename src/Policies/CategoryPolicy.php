<?php

namespace App\Policies;

use App\Framework\AuthenticatedUser;
use App\Models\Model;

class CategoryPolicy
{
    public function create(?AuthenticatedUser $user, Model $model): bool
    {
        return $user !== null;
    }

    public function update(?AuthenticatedUser $user, Model $model): bool
    {
        return $user !== null;
    }
}