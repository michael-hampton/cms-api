<?php

namespace App\Policies;

use App\Framework\AuthenticatedUser;
use App\Models\Model;

class ProductPolicy
{
    public function create(?AuthenticatedUser $user, Model $model): bool
    {
        return $user->role === 'admin';
    }

    public function update(?AuthenticatedUser $user, Model $model): bool
    {
        return $user->role === 'admin';
    }
}