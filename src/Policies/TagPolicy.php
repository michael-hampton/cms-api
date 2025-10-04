<?php

namespace App\Policies;

use App\Framework\AuthenticatedUser;
use App\Models\Model;

class TagPolicy
{
    public function create(?AuthenticatedUser $user, Model $model): bool
    {
        return $user !== null;
    }
}